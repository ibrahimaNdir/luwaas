<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\BailLocataireResource;
use App\Http\Resources\BailProprietaireRessource;
use App\Events\BailCree;
use App\Models\Bail;
use App\Models\Logement;
use App\Models\Paiement;
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
    $proprietaire_id = $user->proprietaire->id ?? null;

    if (!$proprietaire_id) {
        return response()->json(['message' => 'Non autorisé.'], 403);
    }

    // ✅ VALIDATION : demande_id est OBLIGATOIRE
    $validated = $request->validate([
        'demande_id'               => 'required|exists:demandes,id', // ✅ OBLIGATOIRE
        'montant_loyer'            => 'required|integer|min:1000',
        'charges_mensuelles'       => 'required|integer|min:0',
        'nombre_mois_caution'      => 'required|integer|min:1|max:6',
        'date_debut'               => 'required|date|after_or_equal:today',
        'date_fin'                 => 'required|date|after:date_debut',
        'jour_echeance'            => 'required|integer|min:1|max:31',
        'renouvellement_automatique' => 'required|boolean',
        'conditions_speciales'     => 'nullable|string|max:2000',
    ]);

    // ✅ RÉCUPÉRER LA DEMANDE
    $demande = Demande::with('logement.propriete')->findOrFail($validated['demande_id']);

    // ✅ VÉRIFICATION 1 : La demande doit être ACCEPTÉE
    if ($demande->status !== 'acceptee') {
        return response()->json([
            'message' => 'La demande doit être acceptée avant de créer un bail.',
            'statut_actuel' => $demande->status,
        ], 422);
    }

    // ✅ VÉRIFICATION 2 : La demande doit concerner un logement du bailleur
    if ($demande->proprietaire_id !== $proprietaire_id) {
        return response()->json(['message' => 'Cette demande ne vous concerne pas.'], 403);
    }

    // ✅ EXTRAIRE logement_id et locataire_id de la demande
    $logementId = $demande->logement_id;
    $locataireId = $demande->locataire_id;

    // ✅ VÉRIFICATION 3 : Le logement doit être disponible
    if ($demande->logement->statut_occupe !== 'disponible') {
        return response()->json(['message' => 'Ce logement n\'est plus disponible.'], 422);
    }

    // ✅ VÉRIFICATION 4 : Pas de bail actif existant
    $bailExistant = Bail::where('logement_id', $logementId)
        ->whereIn('statut', ['en_attente_paiement', 'actif'])
        ->exists();

    if ($bailExistant) {
        return response()->json([
            'message' => 'Un bail actif existe déjà pour ce logement.'
        ], 422);
    }

    // Calcul caution
    $montant_caution_total = $validated['montant_loyer'] * $validated['nombre_mois_caution'];

    // ✅ CRÉATION DU BAIL
    $bail = Bail::create([
        'logement_id'              => $logementId,
        'locataire_id'             => $locataireId,
        'demande_id'               => $demande->id,
        'montant_loyer'            => $validated['montant_loyer'],
        'charges_mensuelles'       => $validated['charges_mensuelles'],
        'nombre_mois_caution'      => $validated['nombre_mois_caution'],
        'montant_caution_total'    => $montant_caution_total,
        'date_debut'               => $validated['date_debut'],
        'date_fin'                 => $validated['date_fin'],
        'jour_echeance'            => $validated['jour_echeance'],
        'renouvellement_automatique' => $validated['renouvellement_automatique'],
        'conditions_speciales'     => $validated['conditions_speciales'] ?? null,
        'statut'                   => 'en_attente_paiement',
    ]);

    // Paiement de signature
    $montantTotalSignature = $montant_caution_total + $validated['montant_loyer'];
    $periode = Carbon::parse($validated['date_debut'])->isoFormat('MMMM YYYY');

    Paiement::create([
        'locataire_id'    => $locataireId,
        'bail_id'         => $bail->id,
        'type'            => 'signature',
        'montant_attendu' => $montantTotalSignature,
        'montant_paye'    => 0,
        'montant_restant' => $montantTotalSignature,
        'statut'          => 'impayé',
        'date_echeance'   => now()->addDays(7),
        'periode'         => $periode,
    ]);

    // ✅ METTRE À JOUR LA DEMANDE
    $demande->update(['status' => 'bail_cree']);

    event(new BailCree($bail));

    Log::info("✅ Bail créé : ID {$bail->id} depuis demande {$demande->id}");

    return response()->json([
        'success' => true,
        'message' => 'Bail créé avec succès. Le locataire a été notifié.',
        'bail' => [
            'id'                     => $bail->id,
            'statut'                 => $bail->statut,
            'demande_id'             => $demande->id,
            'montant_total_signature' => $montantTotalSignature,
            'periode_couverte'       => $periode,
            'details' => [
                'caution'       => $montant_caution_total,
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

    // ✅ MÉTHODE activerBail() SUPPRIMÉE
    // La logique d'activation est dans WebhookController

    /**
     * ═══════════════════════════════════════════════════════════════
     * MÉTHODES DE CONSULTATION
     * ═══════════════════════════════════════════════════════════════
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

    public function show($id)
    {
        $user = auth()->user();

        $bail = Bail::with([
            'locataire.user',
            'logement.propriete.proprietaire.user',
            'paiements'
        ])->findOrFail($id);

        $isLocataire = $bail->locataire && $bail->locataire->user_id === $user->id;
        $isBailleur = $bail->logement->propriete->proprietaire
            && $bail->logement->propriete->proprietaire->user_id === $user->id;

        if (!$isLocataire && !$isBailleur) {
            return response()->json(['message' => 'Non autorisé.'], 403);
        }

        return new BailLocataireResource($bail);
    }

    public function exportPdf($bailId)
    {
        $user = auth()->user();

        $bail = Bail::with([
            'locataire.user',
            'logement.propriete.proprietaire.user'
        ])->findOrFail($bailId);

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