<?php

namespace App\Services;

use App\Models\Bail;
use App\Models\Paiement;
use Illuminate\Database\Eloquent\Collection;

class PaiementService
{
    /**
     * Stats globales d'un locataire.
     */
    public function statistiquesLocataire(int $locataireId): array
    {
        $base = fn() => Paiement::where('locataire_id', $locataireId);

        return [
            'total_paiements'       => $base()->count(),
            'payes'                 => $base()->where('statut', 'payé')->count(),
            'impayes'               => $base()->where('statut', 'impayé')->count(),
            'en_retard'             => $base()->where('statut', 'en_retard')->count(),
            'montant_total_paye'    => $base()->where('statut', 'payé')->sum('montant_paye'),
            'montant_total_restant' => $base()->whereIn('statut', ['impayé', 'en_retard', 'partiel'])
                                              ->sum('montant_restant'),
        ];
    }

    /**
     * Prochain paiement à régler pour un bail donné.
     */
    public function prochainPaiementARegler(int $bailId): ?Paiement
    {
        return Paiement::where('bail_id', $bailId)
            ->whereIn('statut', ['impayé', 'en_retard', 'partiel'])
            ->orderByRaw("CASE WHEN type = 'signature' THEN 0 ELSE 1 END")
            ->orderBy('date_echeance', 'asc')
            ->first();
    }

    /**
     * Formate la réponse détaillée d'un paiement payé.
     */
    public function formatPaiementPaye(Paiement $paiement): array
    {
        $paiement->load(['transactions' => fn($q) => $q
            ->where('statut', 'valide')
            ->orderByDesc('date_transaction')
            ->limit(1)
        ]);

        $transaction = $paiement->transactions->first();

        return [
            'paiement' => $this->formatBasePaiement($paiement),
            'transaction' => $transaction ? [
                'id'              => $transaction->id,
                'reference'       => $transaction->reference,
                'mode_paiement'   => $transaction->mode_paiement,
                'montant'         => $transaction->montant,
                'statut'          => $transaction->statut,
                'date_transaction' => $transaction->date_transaction,
            ] : null,
            'peut_payer' => false,
            'message'    => '✅ Ce paiement a été effectué avec succès',
        ];
    }

    /**
     * Formate la réponse détaillée d'un paiement non réglé.
     */
    public function formatPaiementImpaye(Paiement $paiement): array
    {
        $paiement->load(['transactions' => fn($q) => $q
            ->whereIn('statut', ['en_attente', 'rejete', 'echoue'])
            ->orderByDesc('created_at')
        ]);

        return [
            'paiement' => array_merge(
                $this->formatBasePaiement($paiement),
                ['montant_restant' => $paiement->montant_restant]
            ),
            'transactions_precedentes' => $paiement->transactions->map(fn($t) => [
                'id'            => $t->id,
                'reference'     => $t->reference,
                'mode_paiement' => $t->mode_paiement,
                'statut'        => $t->statut,
                'date'          => $t->created_at,
            ]),
            'peut_payer'                  => true,
            'modes_paiement_disponibles'  => ['wave', 'orange_money', 'free_money', 'paypal'],
            'message'                     => '⚠️ Ce paiement est en attente de règlement',
        ];
    }

    // ─── Helpers privés ───────────────────────────────────────────

    private function formatBasePaiement(Paiement $paiement): array
    {
        return [
            'id'              => $paiement->id,
            'type'            => $paiement->type,
            'periode'         => $paiement->periode,
            'montant_attendu' => $paiement->montant_attendu,
            'montant_paye'    => $paiement->montant_paye,
            'statut'          => $paiement->statut,
            'date_echeance'   => $paiement->date_echeance,
            'date_paiement'   => $paiement->date_paiement ?? null,
            'bail' => [
                'id'      => $paiement->bail->id,
                'logement' => $paiement->bail->logement->numero ?? 'N/A',
                'type'    => $paiement->bail->logement->typelogement ?? 'N/A',
            ],
        ];
    }
}