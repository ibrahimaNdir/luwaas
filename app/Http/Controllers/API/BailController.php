<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\BailLocataireResource;
use App\Http\Resources\BailProprietaireRessource;
use App\Events\BailCree;
use App\Events\BailSigne;
use App\Models\Bail;
use App\Models\Logement;
use App\Models\Paiement;
use App\Models\Transaction;
use App\Models\Demande;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class BailController extends Controller
{
    /**
     * ═══════════════════════════════════════════════════════════════
     * 1. CRÉATION D'UN BAIL (Côté Bailleur)
     * ═══════════════════════════════════════════════════════════════
     */
    public function store(Request $request)
    {
        $user = $request->user();
        
        // Vérifier que c'est un propriétaire
        $proprietaire_id = $user->proprietaire->id ?? null;
        if (!$proprietaire_id) {
            return response()->json(['message' => 'Non autorisé.'], 403);
        }

        $validated = $request->validate([
            'logement_id' => 'required|exists:logements,id',
            'locataire_id' => 'required|exists:locataires,id',
            'demande_id' => 'nullable|exists:demandes,id',
            
            // Finances
            'montant_loyer' => 'required|integer|min:1000',
            'charges_mensuelles' => 'required|integer|min:0',
            'nombre_mois_caution' => 'required|integer|min:1|max:6',
            
            // Dates
            'date_debut' => 'required|date|after_or_equal:today',
            'date_fin' => 'required|date|after:date_debut',
            'jour_echeance' => 'required|integer|min:1|max:31',
            'renouvellement_automatique' => 'required|boolean',
            
            // Optionnel
            'conditions_speciales' => 'nullable|string|max:2000',
        ]);

        // Vérifier que le logement appartient au propriétaire
        $logement = Logement::with('propriete')->findOrFail($validated['logement_id']);
        
        if ($logement->propriete->proprietaire_id !== $proprietaire_id) {
            return response()->json(['message' => 'Ce logement ne vous appartient pas.'], 403);
        }

        // Vérifier que le logement est disponible
        if (isset($logement->statut) && $logement->statut !== 'disponible') {
            return response()->json(['message' => 'Ce logement n\'est pas disponible.'], 422);
        }

        // Calculer la caution totale
        $montant_caution_total = ($validated['montant_loyer'] + $validated['charges_mensuelles']) 
                                * $validated['nombre_mois_caution'];

        // Création du bail
        $bail = Bail::create([
            'logement_id' => $validated['logement_id'],
            'locataire_id' => $validated['locataire_id'],
            'demande_id' => $validated['demande_id'] ?? null,
            
            'montant_loyer' => $validated['montant_loyer'],
            'charges_mensuelles' => $validated['charges_mensuelles'],
            'nombre_mois_caution' => $validated['nombre_mois_caution'],
            'montant_caution_total' => $montant_caution_total,
            
            'date_debut' => $validated['date_debut'],
            'date_fin' => $validated['date_fin'],
            'jour_echeance' => $validated['jour_echeance'],
            'renouvellement_automatique' => $validated['renouvellement_automatique'],
            
            'conditions_speciales' => $validated['conditions_speciales'] ?? null,
            
            'statut' => 'en_attente_paiement',
        ]);

        // Créer le paiement de SIGNATURE (caution + 1er mois)
        $montantTotalSignature = $montant_caution_total + $validated['montant_loyer'];
        
        Paiement::create([
            'locataire_id' => $bail->locataire_id,
            'bail_id' => $bail->id,
            'type' => 'signature',
            'montant_attendu' => $montantTotalSignature,
            'montant_paye' => 0,
            'montant_restant' => $montantTotalSignature,
            'statut' => 'impayé',
            'date_echeance' => now()->addDays(7),
            'periode' => 'Signature du bail',
        ]);

        // Mettre à jour la demande si fournie
        if (!empty($validated['demande_id'])) {
            Demande::find($validated['demande_id'])->update(['status' => 'bail_cree']);
        }

        // ✅ DISPATCH EVENT (notification au locataire)
        event(new BailCree($bail));

        Log::info("✅ Bail créé : ID {$bail->id}, Montant signature : {$montantTotalSignature} FCFA");

        return response()->json([
            'success' => true,
            'message' => 'Bail créé avec succès. Le locataire a été notifié.',
            'bail' => [
                'id' => $bail->id,
                'statut' => $bail->statut,
                'montant_total_signature' => $montantTotalSignature,
                'details' => [
                    'caution' => $montant_caution_total,
                    'premier_loyer' => $validated['montant_loyer'],
                ],
                'date_limite_paiement' => now()->addDays(7)->format('Y-m-d'),
            ]
        ], 201);
    }

    /**
     * ═══════════════════════════════════════════════════════════════
     * 2. VOIR LE BAIL EN ATTENTE (Côté Locataire)
     * ═══════════════════════════════════════════════════════════════
     */
    public function getBailEnAttente(Request $request)
    {
        $user = $request->user();
        $locataire_id = $user->locataire->id ?? null;

        if (!$locataire_id) {
            return response()->json(['message' => 'Non autorisé.'], 403);
        }

        $bail = Bail::with(['logement.propriete.proprietaire.user', 'paiements'])
            ->where('locataire_id', $locataire_id)
            ->where('statut', 'en_attente_paiement')
            ->first();

        if (!$bail) {
            return response()->json(['message' => 'Aucun bail en attente de signature.'], 404);
        }

        return response()->json([
            'bail' => [
                'id' => $bail->id,
                'logement' => [
                    'type' => $bail->logement->typelogement ?? '',
                    'numero' => $bail->logement->numero ?? '',
                    'adresse' => $bail->logement->propriete->adresse ?? '',
                ],
                'bailleur' => [
                    'nom' => $bail->logement->propriete->proprietaire->user->nom ?? '',
                    'prenom' => $bail->logement->propriete->proprietaire->user->prenom ?? '',
                    'telephone' => $bail->logement->propriete->proprietaire->user->telephone ?? '',
                ],
                'finances' => [
                    'loyer_mensuel' => $bail->montant_loyer,
                    'charges' => $bail->charges_mensuelles,
                    'caution' => $bail->montant_caution_total,
                    'total_a_payer' => $bail->montant_caution_total + $bail->montant_loyer,
                ],
                'dates' => [
                    'debut' => $bail->date_debut,
                    'fin' => $bail->date_fin,
                    'duree_mois' => Carbon::parse($bail->date_debut)->diffInMonths($bail->date_fin),
                ],
                'conditions_speciales' => $bail->conditions_speciales,
            ]
        ]);
    }

    /**
     * ═══════════════════════════════════════════════════════════════
     * 3. INITIER LE PAIEMENT MOBILE MONEY (Côté Locataire)
     * ═══════════════════════════════════════════════════════════════
     */
    public function initierPaiement(Request $request, $bailId)
    {
        $user = $request->user();
        $locataire_id = $user->locataire->id ?? null;

        if (!$locataire_id) {
            return response()->json(['message' => 'Non autorisé.'], 403);
        }

        $validated = $request->validate([
            'operateur' => 'required|in:wave,orange_money,free_money,paypal',
            'telephone' => 'nullable|string|regex:/^\+221[0-9]{9}$/',
        ]);

        // Vérifier que le bail appartient au locataire
        $bail = Bail::where('id', $bailId)
            ->where('locataire_id', $locataire_id)
            ->where('statut', 'en_attente_paiement')
            ->firstOrFail();

        // Récupérer le paiement de signature
        $paiement = Paiement::where('bail_id', $bailId)
            ->where('type', 'signature')
            ->firstOrFail();

        // Vérifier qu'il n'y a pas déjà une transaction en attente
        $transactionEnCours = Transaction::where('paiement_id', $paiement->id)
            ->where('statut', 'en_attente')
            ->first();

        if ($transactionEnCours) {
            return response()->json([
                'message' => 'Une transaction est déjà en cours pour ce paiement.'
            ], 422);
        }

        // Créer la transaction
        $transaction = Transaction::create([
            'paiement_id' => $paiement->id,
            'mode_paiement' => $validated['operateur'],
            'montant' => $paiement->montant_attendu,
            'statut' => 'en_attente',
            'telephone_payeur' => $validated['telephone'] ?? null,
            'reference' => 'BAIL-' . $bail->id . '-' . time(),
            'date_transaction' => now(),
        ]);

        Log::info("💳 Paiement initié : Bail {$bailId}, Transaction {$transaction->id}, Montant {$paiement->montant_attendu} FCFA");

        // TODO: Appeler l'API du fournisseur de paiement (Wave/OM/PayPal)
        // et retourner l'URL de redirection

        return response()->json([
            'success' => true,
            'message' => 'Paiement initié. Veuillez entrer votre code PIN.',
            'transaction' => [
                'id' => $transaction->id,
                'reference' => $transaction->reference,
                'montant' => $transaction->montant,
                'operateur' => $transaction->mode_paiement,
            ],
            // 'payment_url' => $urlDeRedirection, // À implémenter
        ]);
    }

    /**
     * ═══════════════════════════════════════════════════════════════
     * 4. ACTIVER LE BAIL (Appelé par Webhook)
     * ═══════════════════════════════════════════════════════════════
     */
    public function activerBail($bailId, $transactionId)
    {
        $bail = Bail::with(['logement', 'demande'])->findOrFail($bailId);
        $transaction = Transaction::findOrFail($transactionId);
        $paiement = $transaction->paiement;

        Log::info("🔄 Activation du bail {$bailId} via transaction {$transactionId}");

        // 1. Mettre à jour la transaction
        $transaction->update([
            'statut' => 'valide',
            'date_transaction' => now(),
        ]);

        // 2. Mettre à jour le paiement de signature
        $paiement->update([
            'montant_paye' => $transaction->montant,
            'montant_restant' => 0,
            'statut' => 'payé',
            'date_paiement' => now(),
        ]);

        // 3. Mettre à jour le bail
        $bail->update(['statut' => 'actif']);

        // 4. Générer les paiements mensuels de loyer
        $this->genererPaiementsMensuels($bail);

        // 5. Marquer le logement comme loué
        $bail->logement->update(['statut' => 'loue']);

        // 6. Mettre à jour la demande
        if ($bail->demande_id) {
            Demande::find($bail->demande_id)->update(['status' => 'bail_signe']);
        }

        // 7. Générer le PDF du bail
        $this->genererBailPDF($bail);

        // ✅ 8. DISPATCH EVENT (notifications aux 2 parties)
        event(new BailSigne($bail));

        Log::info("✅ Bail {$bailId} activé avec succès");

        return $bail;
    }

    /**
     * ═══════════════════════════════════════════════════════════════
     * MÉTHODES UTILITAIRES
     * ═══════════════════════════════════════════════════════════════
     */

    /**
     * Générer les paiements mensuels de loyer
     */
/**
     * Générer UNIQUEMENT le prochain paiement de loyer après la signature
     */
    private function genererPaiementsMensuels(Bail $bail)
    {
        // On calcule la date du prochain loyer (1 mois après la date de début)
        $prochainMois = Carbon::parse($bail->date_debut)->addMonth();
        
        // On ajuste au jour d'échéance choisi par le bailleur
        $jour = min($bail->jour_echeance, $prochainMois->copy()->endOfMonth()->day);
        $dateEcheance = $prochainMois->copy()->day($jour);
        
        $periode = $dateEcheance->isoFormat('MMMM YYYY'); // ex: Février 2026

        Log::info("📅 Génération du 1er loyer régulier pour bail {$bail->id} (Échéance: {$dateEcheance->format('Y-m-d')})");

        Paiement::create([
            'locataire_id' => $bail->locataire_id,
            'bail_id' => $bail->id,
            'type' => 'loyer_mensuel',
            'montant_attendu' => $bail->montant_loyer + $bail->charges_mensuelles,
            'montant_paye' => 0,
            'montant_restant' => $bail->montant_loyer + $bail->charges_mensuelles,
            'statut' => 'impayé',
            'date_echeance' => $dateEcheance,
            'periode' => $periode,
        ]);
    }

    /**
     * Générer le PDF du bail
     */
    private function genererBailPDF(Bail $bail)
    {
        try {
            $bail->load(['locataire.user', 'logement.propriete.proprietaire.user']);

            $pdf = PDF::loadView('bail_pdf', compact('bail'));
            
            $filename = "bail_{$bail->id}_" . now()->format('Ymd_His') . ".pdf";
            $path = "baux/{$filename}";
            
            Storage::disk('public')->put($path, $pdf->output());
            
            $bail->update(['document_pdf_path' => $path]);

            Log::info("📄 PDF généré : {$path}");
        } catch (\Exception $e) {
            Log::error("❌ Erreur génération PDF : " . $e->getMessage());
        }
    }

    /**
     * ═══════════════════════════════════════════════════════════════
     * MÉTHODES DE CONSULTATION
     * ═══════════════════════════════════════════════════════════════
     */

    /**
     * Liste des baux du bailleur
     */
    public function bauxBailleur(Request $request)
    {
        $user = $request->user();
        $proprietaire_id = $user->proprietaire->id ?? null;

        if (!$proprietaire_id) {
            return response()->json(['message' => 'Non autorisé.'], 403);
        }

        $baux = Bail::with(['locataire.user', 'logement'])
            ->whereHas('logement.propriete', function ($q) use ($proprietaire_id) {
                $q->where('proprietaire_id', $proprietaire_id);
            })
            ->orderByDesc('created_at')
            ->get();

        return BailProprietaireRessource::collection($baux);
    }

    /**
     * Liste des baux du locataire
     */
    public function bauxLocataire(Request $request)
    {
        $user = $request->user();
        $locataire_id = $user->locataire->id ?? null;

        if (!$locataire_id) {
            return response()->json(['message' => 'Non autorisé.'], 403);
        }

        $baux = Bail::with(['logement.propriete'])
            ->where('locataire_id', $locataire_id)
            ->orderByDesc('created_at')
            ->get();

        return BailLocataireResource::collection($baux);
    }

    /**
     * Détails d'un bail
     */
    public function show($id)
    {
        $user = auth()->user();
        
        $bail = Bail::with([
            'locataire.user',
            'logement.propriete.proprietaire.user',
            'paiements'
        ])->findOrFail($id);

        // Vérifier que l'utilisateur est partie prenante
        $isLocataire = $bail->locataire && $bail->locataire->user_id === $user->id;
        $isBailleur = $bail->logement->propriete->proprietaire 
                    && $bail->logement->propriete->proprietaire->user_id === $user->id;

        if (!$isLocataire && !$isBailleur) {
            return response()->json(['message' => 'Non autorisé.'], 403);
        }

        return new BailLocataireResource($bail);
    }

    /**
     * Télécharger le PDF du bail
     */
    public function exportPdf($bailId)
    {
        $user = auth()->user();

        $bail = Bail::with([
            'locataire.user',
            'logement.propriete.proprietaire.user'
        ])->findOrFail($bailId);

        // Vérification sécurité
        $isLocataire = $bail->locataire && $bail->locataire->user_id === $user->id;
        $isBailleur = $bail->logement->propriete->proprietaire 
                    && $bail->logement->propriete->proprietaire->user_id === $user->id;

        if (!$isLocataire && !$isBailleur) {
            return response()->json(['message' => 'Non autorisé.'], 403);
        }

        if ($bail->statut !== 'actif') {
            return response()->json([
                'message' => 'Le bail doit être signé pour télécharger le PDF.'
            ], 422);
        }

        $pdf = PDF::loadView('bail_pdf', compact('bail'));
        return $pdf->download('Contrat_Location_' . $bail->id . '.pdf');
    }

    /**
     * Supprimer un bail
     */
    public function destroy($id)
    {
        $bail = Bail::findOrFail($id);
        
        if ($bail->statut === 'actif') {
            return response()->json([
                'message' => 'Impossible de supprimer un bail actif.'
            ], 422);
        }

        $bail->delete();

        return response()->json([
            'success' => true,
            'message' => 'Bail supprimé avec succès.'
        ]);
    }
}