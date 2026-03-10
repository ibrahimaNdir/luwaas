<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\PaiementLocataireRessource;
use App\Http\Resources\PaiementDetailsRessources;
use App\Http\Resources\PaiementProprietaireRessource;
use App\Models\Bail;
use App\Models\Paiement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaiementController extends Controller
{
    /**
     * ═══════════════════════════════════════════════════════════════
     * CONSULTATION DES PAIEMENTS (Côté Locataire)
     * ═══════════════════════════════════════════════════════════════
     */

    /**
     * Liste de tous les paiements du locataire connecté
     * GET /api/locataire/paiements
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $locataire_id = $user->locataire->id ?? null;

        if (!$locataire_id) {
            return response()->json(['message' => 'Non autorisé.'], 403);
        }

        $paiements = Paiement::with(['bail.logement', 'transactions'])
            ->where('locataire_id', $locataire_id)
            ->orderByDesc('date_echeance')
            ->get();

        return PaiementLocataireRessource::collection($paiements);
    }

    /**
     * Détails d'un paiement spécifique
     * GET /api/paiements/{id}
     */
    public function show($id, Request $request)
    {
        $user = $request->user();
        $locataire_id = $user->locataire->id ?? null;

        if (!$locataire_id) {
            return response()->json(['message' => 'Non autorisé.'], 403);
        }

        $paiement = Paiement::with(['bail.logement'])
            ->where('id', $id)
            ->whereHas('bail', function ($q) use ($locataire_id) {
                $q->where('locataire_id', $locataire_id);
            })
            ->firstOrFail();

        // ✅ CAS 1 : Paiement PAYÉ
        if ($paiement->statut === 'payé') {
            // Charger la transaction validée
            $paiement->load(['transactions' => function ($q) {
                $q->where('statut', 'valide')
                    ->orderByDesc('date_transaction')
                    ->limit(1);
            }]);

            $transaction = $paiement->transactions->first();

            return response()->json([
                'paiement' => [
                    'id' => $paiement->id,
                    'type' => $paiement->type,
                    'periode' => $paiement->periode,
                    'montant_attendu' => $paiement->montant_attendu,
                    'montant_paye' => $paiement->montant_paye,
                    'statut' => $paiement->statut,
                    'date_paiement' => $paiement->date_paiement,
                    'date_echeance' => $paiement->date_echeance,
                    'bail' => [
                        'id' => $paiement->bail->id,
                        'logement' => $paiement->bail->logement->numero ?? 'N/A',
                        'type' => $paiement->bail->logement->typelogement ?? 'N/A',
                    ],
                ],
                'transaction' => $transaction ? [
                    'id' => $transaction->id,
                    'reference' => $transaction->reference,
                    'mode_paiement' => $transaction->mode_paiement,
                    'montant' => $transaction->montant,
                    'statut' => $transaction->statut,
                    'date_transaction' => $transaction->date_transaction,
                ] : null,
                'peut_payer' => false,
                'message' => '✅ Ce paiement a été effectué avec succès',
            ]);
        }

        // ✅ CAS 2 : Paiement IMPAYÉ / EN RETARD / PARTIEL
        else {
            // Charger les transactions échouées ou en attente
            $paiement->load(['transactions' => function ($q) {
                $q->whereIn('statut', ['en_attente', 'rejete', 'echoue'])
                    ->orderByDesc('created_at');
            }]);

            return response()->json([
                'paiement' => [
                    'id' => $paiement->id,
                    'type' => $paiement->type,
                    'periode' => $paiement->periode,
                    'montant_attendu' => $paiement->montant_attendu,
                    'montant_paye' => $paiement->montant_paye,
                    'montant_restant' => $paiement->montant_restant,
                    'statut' => $paiement->statut,
                    'date_echeance' => $paiement->date_echeance,
                    'bail' => [
                        'id' => $paiement->bail->id,
                        'logement' => $paiement->bail->logement->numero ?? 'N/A',
                        'type' => $paiement->bail->logement->typelogement ?? 'N/A',
                    ],
                ],
                'transactions_precedentes' => $paiement->transactions->map(function ($t) {
                    return [
                        'id' => $t->id,
                        'reference' => $t->reference,
                        'mode_paiement' => $t->mode_paiement,
                        'statut' => $t->statut,
                        'date' => $t->created_at,
                    ];
                }),
                'peut_payer' => true,
                'modes_paiement_disponibles' => [
                    'wave',
                    'orange_money',
                    'free_money',
                    'paypal',
                ],
                'message' => '⚠️ Ce paiement est en attente de règlement',
            ]);
        }
    }

    /**
     * Liste des paiements d'un bail spécifique
     * GET /api/baux/{bailId}/paiements
     */
    public function paiementsBail($bailId, Request $request)
    {
        $user = $request->user();
        $locataire_id = $user->locataire->id ?? null;

        if (!$locataire_id) {
            return response()->json(['message' => 'Non autorisé.'], 403);
        }

        // Vérifier que le bail appartient au locataire
        $bail = Bail::where('id', $bailId)
            ->where('locataire_id', $locataire_id)
            ->firstOrFail();

        $paiements = Paiement::with('transactions')
            ->where('bail_id', $bailId)
            ->orderBy('date_echeance', 'asc')
            ->get();

        return PaiementLocataireRessource::collection($paiements);
    }

    /**
     * Prochain paiement à régler pour un bail
     * GET /api/baux/{bailId}/paiement-a-regler
     * 
     * Utilisé par Flutter pour afficher "Vous devez payer X FCFA"
     */
    public function paiementARegler($bailId, Request $request)
    {
        $user = $request->user();
        $locataire_id = $user->locataire->id ?? null;

        if (!$locataire_id) {
            return response()->json(['message' => 'Non autorisé.'], 403);
        }

        $bail = Bail::where('id', $bailId)
            ->where('locataire_id', $locataire_id)
            ->firstOrFail();

        // ✅ Prioriser la signature si elle n'est pas encore payée
        $paiement = Paiement::where('bail_id', $bailId)
            ->whereIn('statut', ['impayé', 'en_retard', 'partiel'])
            ->orderByRaw("CASE WHEN type = 'signature' THEN 0 ELSE 1 END") // signature en premier
            ->orderBy('date_echeance', 'asc')
            ->first();

        if (!$paiement) {
            return response()->json([
                'message'     => 'Tous les paiements sont réglés pour ce bail !',
                'tous_payes'  => true,
            ], 200);
        }

        return response()->json([
            'tous_payes' => false,
            'paiement'   => [
                'id'              => $paiement->id,
                'type'            => $paiement->type,
                'montant_attendu' => $paiement->montant_attendu,
                'montant_restant' => $paiement->montant_restant,
                'periode'         => $paiement->periode,
                'date_echeance'   => $paiement->date_echeance,
                'statut'          => $paiement->statut,
            ],
            'bail' => [
                'id'      => $bail->id,
                'logement' => $bail->logement->numero ?? null,
            ],
        ]);
    }



    /**
     * ═══════════════════════════════════════════════════════════════
     * STATISTIQUES PAIEMENTS (Optionnel)
     * ═══════════════════════════════════════════════════════════════
     */

    /**
     * Statistiques des paiements du locataire
     * GET /api/locataire/paiements/stats
     */
    public function statistiques(Request $request)
    {
        $user = $request->user();
        $locataire_id = $user->locataire->id ?? null;

        if (!$locataire_id) {
            return response()->json(['message' => 'Non autorisé.'], 403);
        }

        $stats = [
            'total_paiements' => Paiement::where('locataire_id', $locataire_id)->count(),
            'payes' => Paiement::where('locataire_id', $locataire_id)->where('statut', 'payé')->count(),
            'impayes' => Paiement::where('locataire_id', $locataire_id)->where('statut', 'impayé')->count(),
            'en_retard' => Paiement::where('locataire_id', $locataire_id)->where('statut', 'en_retard')->count(),
            'montant_total_paye' => Paiement::where('locataire_id', $locataire_id)
                ->where('statut', 'payé')
                ->sum('montant_paye'),
            'montant_total_restant' => Paiement::where('locataire_id', $locataire_id)
                ->whereIn('statut', ['impayé', 'en_retard', 'partiel'])
                ->sum('montant_restant'),
        ];

        return response()->json($stats);
    }

    /**
     * ═══════════════════════════════════════════════════════════════
     * CONSULTATION CÔTÉ BAILLEUR
     * ═══════════════════════════════════════════════════════════════
     */

    /**
     * Liste des paiements reçus par le bailleur
     * GET /api/proprietaire/paiements
     */
    public function paiementsProprietaire(Request $request)
    {
        $user = $request->user();
        $proprietaire_id = $user->proprietaire->id ?? null;

        if (!$proprietaire_id) {
            return response()->json(['message' => 'Non autorisé.'], 403);
        }

        $paiements = Paiement::with(['bail.logement', 'locataire.user', 'transactions'])
            ->whereHas('bail.logement.propriete', function ($query) use ($proprietaire_id) {
                $query->where('proprietaire_id', $proprietaire_id);
            })
            ->when($request->statut, fn($q) => $q->where('statut', $request->statut)) // ✅ filtre optionnel
            ->orderByDesc('date_echeance')
            ->paginate(20);


        return PaiementProprietaireRessource::collection($paiements);
    }
}
