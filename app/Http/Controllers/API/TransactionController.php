<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Paiement;
use App\Models\Transaction;
use App\Services\TransactionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TransactionController extends Controller
{
    public function __construct(protected TransactionService $transactionService) {}

    // ═══════════════════════════════════════════
    // INITIER UN PAIEMENT
    // ═══════════════════════════════════════════

    public function initierPaiement(Request $request, $paiementId)
    {
        $locataireId = $this->locataireId($request);

        $validated = $request->validate([
            'operateur' => 'required|in:wave,orange_money,free_money,paypal',
            'telephone' => 'nullable|string|max:20',
        ]);

        $paiement = Paiement::with('bail')->findOrFail($paiementId);

        $erreur = $this->transactionService->validerInitiation($paiement, $locataireId);
        if ($erreur) {
            return response()->json(
                array_diff_key($erreur, ['status' => '']),
                $erreur['status']
            );
        }

        try {
            [$transaction, $paymentData] = $this->transactionService->creerEtInitier(
                $paiement,
                $validated['operateur'],
                $validated['telephone'] ?? null,
                $request->ip()
            );
        } catch (\Exception $e) {
            Log::error("❌ Erreur initiation paiement : " . $e->getMessage());
            return response()->json(['message' => 'Erreur lors de l\'initiation. Réessayez.'], 500);
        }

        Log::info("✅ Paiement initié", ['transaction_id' => $transaction->id]);

        return response()->json([
            'success'     => true,
            'message'     => 'Paiement initié avec succès.',
            'transaction' => [
                'id'                => $transaction->id,
                'reference'         => $transaction->reference,
                'reference_externe' => $transaction->reference_externe,
                'montant'           => $transaction->montant,
                'operateur'         => $transaction->mode_paiement,
                'statut'            => $transaction->statut,
            ],
            'payment_data' => $paymentData,
        ], 201);
    }

    // ═══════════════════════════════════════════
    // CONSULTATION
    // ═══════════════════════════════════════════

    public function index(Request $request)
    {
        $locataireId = $this->locataireId($request);

        $transactions = Transaction::with('paiement.bail.logement')
            ->whereHas('paiement', fn($q) => $q->where('locataire_id', $locataireId))
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'transactions' => $transactions->map(
                fn($t) => $this->transactionService->formatPourListe($t)
            )
        ]);
    }

    public function show($id, Request $request)
    {
        $locataireId = $this->locataireId($request);

        $transaction = Transaction::with('paiement.bail.logement')
            ->whereHas('paiement', fn($q) => $q->where('locataire_id', $locataireId))
            ->findOrFail($id);

        return response()->json($this->transactionService->formatPourDetail($transaction));
    }

    public function verifierStatut($id, Request $request)
    {
        $locataireId = $this->locataireId($request);

        $transaction = Transaction::with('paiement')
            ->whereHas('paiement', fn($q) => $q->where('locataire_id', $locataireId))
            ->findOrFail($id);

        return response()->json([
            'transaction_id'   => $transaction->id,
            'statut'           => $transaction->statut,
            'reference'        => $transaction->reference,
            'date_transaction' => $transaction->date_transaction,
            'paiement_statut'  => $transaction->paiement->statut,
        ]);
    }

    // ═══════════════════════════════════════════
    // GESTION
    // ═══════════════════════════════════════════

    public function annuler($id, Request $request)
    {
        $locataireId = $this->locataireId($request);

        $transaction = Transaction::whereHas('paiement', fn($q) => $q->where('locataire_id', $locataireId))
            ->findOrFail($id);

        if ($transaction->statut !== 'en_attente') {
            return response()->json(['message' => 'Seules les transactions en attente peuvent être annulées.'], 422);
        }

        $transaction->update(['statut' => 'rejete']);

        Log::info("🚫 Transaction {$id} annulée par le locataire");

        return response()->json(['success' => true, 'message' => 'Transaction annulée avec succès.']);
    }

    public function relancer($id, Request $request)
    {
        $locataireId = $this->locataireId($request);

        $ancienne = Transaction::with('paiement')
            ->whereHas('paiement', fn($q) => $q->where('locataire_id', $locataireId))
            ->findOrFail($id);

        if ($ancienne->statut !== 'rejete') {
            return response()->json(['message' => 'Seules les transactions échouées peuvent être relancées.'], 422);
        }

        try {
            [$nouvelle, $paymentData] = $this->transactionService->relancer($ancienne, $request->ip());
        } catch (\Exception $e) {
            Log::error("❌ Erreur relance paiement : " . $e->getMessage());
            return response()->json(['message' => 'Erreur lors de la relance. Réessayez.'], 500);
        }

        return response()->json([
            'success'     => true,
            'message'     => 'Nouvelle transaction créée.',
            'transaction' => [
                'id'        => $nouvelle->id,
                'reference' => $nouvelle->reference,
                'montant'   => $nouvelle->montant,
            ],
            'payment_data' => $paymentData,
        ]);
    }

    // ═══════════════════════════════════════════
    // HELPER PRIVÉ
    // ═══════════════════════════════════════════

    private function locataireId(Request $request): int
    {
        $id = $request->user()->locataire->id ?? null;

        abort_if(!$id, 403, 'Non autorisé.');

        return $id;
    }
}