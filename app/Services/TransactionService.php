<?php

namespace App\Services;

use App\Models\Proprietaire;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Collection;

class TransactionService
{
  
    // STATUT ABONNEMENT
    
    public function statutAbonnement(Proprietaire $proprietaire): array
    {
        return $proprietaire->subscriptionSummary();
    }

    // QUERIES
    
    public function getTransactionsLocataire(int $locataireId): Collection
    {
        return Transaction::with('paiement.bail.logement')
            ->where('type', 'rent_payment')
            ->whereHas('paiement', fn($q) => $q->where('locataire_id', $locataireId))
            ->orderByDesc('created_at')
            ->get();
    }

    public function getTransactionsProprietaire(int $proprietaireId): Collection
    {
        return Transaction::with('subscription.plan')
            ->where('type', 'subscription_payment')
            ->whereHas('subscription', fn($q) => $q->where('proprietaire_id', $proprietaireId))
            ->orderByDesc('created_at')
            ->get();
    }

    public function getAllTransactions(array $filtres = []): Collection
    {
        $query = Transaction::with(['paiement.bail', 'subscription.plan'])
            ->orderByDesc('created_at');

        if (!empty($filtres['type'])) {
            $query->where('type', $filtres['type']);
        }

        if (!empty($filtres['statut'])) {
            $query->where('statut', $filtres['statut']);
        }

        if (!empty($filtres['mode_paiement'])) {
            $query->where('mode_paiement', $filtres['mode_paiement']);
        }

        return $query->get();
    }

    // FORMATAGE
  
    public function formatPourListe(Transaction $t): array
    {
        $data = [
            'id'               => $t->id,
            'type'             => $t->type,
            'reference'        => $t->reference,
            'mode_paiement'    => $t->mode_paiement,
            'montant'          => $t->montant,
            'statut'           => $t->statut,
            'date_transaction' => $t->date_transaction,
        ];

        if ($t->type === 'rent_payment' && $t->paiement) {
            $data['paiement'] = [
                'id'      => $t->paiement->id,
                'type'    => $t->paiement->type,
                'periode' => $t->paiement->periode,
                'statut'  => $t->paiement->statut,
            ];
        }

        if ($t->type === 'subscription_payment' && $t->subscription) {
            $data['subscription'] = [
                'id'     => $t->subscription->id,
                'plan'   => $t->subscription->plan->name ?? null,
                'statut' => $t->subscription->status,
            ];
        }

        return $data;
    }

    public function formatPourDetail(Transaction $t): array
    {
        $data = [
            'transaction' => [
                'id'                => $t->id,
                'type'              => $t->type,
                'reference'         => $t->reference,
                'paydunyatoken'     => $t->paydunyatoken,
                'mode_paiement'     => $t->mode_paiement,
                'montant'           => $t->montant,
                'statut'            => $t->statut,
                'telephone_payeur'  => $t->telephone_payeur,
                'date_transaction'  => $t->date_transaction,
                'created_at'        => $t->created_at,
            ],
        ];

        if ($t->type === 'rent_payment' && $t->paiement) {
            $data['paiement'] = [
                'id'              => $t->paiement->id,
                'type'            => $t->paiement->type,
                'periode'         => $t->paiement->periode,
                'montant_attendu' => $t->paiement->montant_attendu,
                'montant_paye'    => $t->paiement->montant_paye,
                'statut'          => $t->paiement->statut,
            ];

            if ($t->paiement->bail) {
                $data['bail'] = [
                    'id'      => $t->paiement->bail->id,
                    'logement' => $t->paiement->bail->logement->numero ?? null,
                ];
            }
        }

        if ($t->type === 'subscription_payment' && $t->subscription) {
            $data['subscription'] = [
                'id'        => $t->subscription->id,
                'status'    => $t->subscription->status,
                'plan'      => $t->subscription->plan->name ?? null,
                'starts_at' => $t->subscription->starts_at,
                'ends_at'   => $t->subscription->ends_at,
            ];
        }

        return $data;
    }
}