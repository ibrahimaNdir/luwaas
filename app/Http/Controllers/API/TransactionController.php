<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Services\TransactionService;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    public function __construct(protected TransactionService $transactionService) {}

    // ═══════════════════════════════════════════
    // STATUT ABONNEMENT
    // ═══════════════════════════════════════════

    public function statutAbonnement(Request $request)
    {
        $proprietaire = $request->user()->proprietaire ?? null;
        abort_if(!$proprietaire, 403, 'Non autorisé.');

        return response()->json([
            'message'      => 'Statut de l\'abonnement',
            'subscription' => $this->transactionService->statutAbonnement($proprietaire),
        ]);
    }

    // ═══════════════════════════════════════════
    // HISTORIQUE LOCATAIRE (loyers)
    // ═══════════════════════════════════════════

    public function indexLocataire(Request $request)
    {
        $locataireId = $this->locataireId($request);

        $transactions = $this->transactionService->getTransactionsLocataire($locataireId);

        return response()->json([
            'transactions' => $transactions->map(
                fn($t) => $this->transactionService->formatPourListe($t)
            ),
        ]);
    }

    // ═══════════════════════════════════════════
    // HISTORIQUE PROPRIETAIRE (abonnements)
    // ═══════════════════════════════════════════

    public function indexProprietaire(Request $request)
    {
        $proprietaireId = $request->user()->proprietaire->id ?? null;
        abort_if(!$proprietaireId, 403, 'Non autorisé.');

        $transactions = $this->transactionService->getTransactionsProprietaire($proprietaireId);

        return response()->json([
            'transactions' => $transactions->map(
                fn($t) => $this->transactionService->formatPourListe($t)
            ),
        ]);
    }

    // ═══════════════════════════════════════════
    // DÉTAIL
    // ═══════════════════════════════════════════

    public function show(Request $request, int $id)
    {
        $transaction = $this->trouverTransactionAuthorisee($request->user(), $id);

        abort_if(!$transaction, 404, 'Transaction introuvable.');

        return response()->json($this->transactionService->formatPourDetail($transaction));
    }

    // ═══════════════════════════════════════════
    // VÉRIFIER STATUT
    // ═══════════════════════════════════════════

    public function verifierStatut(Request $request, int $id)
    {
        $transaction = $this->trouverTransactionAuthorisee($request->user(), $id);

        abort_if(!$transaction, 404, 'Transaction introuvable.');

        $data = [
            'transaction_id'   => $transaction->id,
            'type'             => $transaction->type,
            'statut'           => $transaction->statut,
            'reference'        => $transaction->reference,
            'date_transaction' => $transaction->date_transaction,
        ];

        if ($transaction->type === 'rent_payment' && $transaction->paiement) {
            $data['paiement_statut'] = $transaction->paiement->statut;
        }

        if ($transaction->type === 'subscription_payment' && $transaction->subscription) {
            $data['subscription_statut'] = $transaction->subscription->status;
        }

        return response()->json($data);
    }

    // ═══════════════════════════════════════════
    // ADMIN
    // ═══════════════════════════════════════════

    public function indexAdmin(Request $request)
    {
        $filtres      = $request->only(['type', 'statut', 'mode_paiement']);
        $transactions = $this->transactionService->getAllTransactions($filtres);

        return response()->json([
            'transactions' => $transactions->map(
                fn($t) => $this->transactionService->formatPourListe($t)
            ),
            'total' => $transactions->count(),
        ]);
    }

    // ═══════════════════════════════════════════
    // HELPERS PRIVÉS
    // ═══════════════════════════════════════════

    private function trouverTransactionAuthorisee($user, int $id): ?Transaction
    {
        if ($user->locataire) {
            return Transaction::where('id', $id)
                ->where('type', 'rent_payment')   // ✅
                ->whereHas('paiement', fn($q) => $q->where('locataire_id', $user->locataire->id))
                ->with('paiement.bail')
                ->first();
        }

        if ($user->proprietaire) {
            return Transaction::where('id', $id)
                ->where('type', 'subscription_payment')  // ✅
                ->whereHas('subscription', fn($q) => $q->where('proprietaire_id', $user->proprietaire->id))
                ->with('subscription.plan')
                ->first();
        }

        return null;
    }

    private function locataireId(Request $request): int
    {
        $id = $request->user()->locataire->id ?? null;
        abort_if(!$id, 403, 'Non autorisé.');
        return $id;
    }
}
