<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\Paiement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class TransactionController extends Controller
{
    /**
     * ═══════════════════════════════════════════════════════════════
     * INITIER UN PAIEMENT MOBILE MONEY (MÉTHODE CRITIQUE)
     * ═══════════════════════════════════════════════════════════════
     */

    public function initierPaiement(Request $request, $paiementId)
    {
        $user = $request->user();
        $locataire_id = $user->locataire->id ?? null;

        if (!$locataire_id) {
            return response()->json(['message' => 'Non autorisé.'], 403);
        }

        $validated = $request->validate([
            'operateur' => 'required|in:wave,orange_money,free_money,paypal',
            'telephone' => 'nullable|string|max:20',
        ]);

        $paiement = Paiement::with('bail')->findOrFail($paiementId);

        if ($paiement->locataire_id !== $locataire_id) {
            return response()->json(['message' => 'Ce paiement ne vous appartient pas.'], 403);
        }

        if ($paiement->statut === 'payé') {
            return response()->json(['message' => 'Ce paiement est déjà réglé.'], 422);
        }

        $transactionEnCours = Transaction::where('paiement_id', $paiementId)
            ->where('statut', 'en_attente')
            ->first();

        if ($transactionEnCours) {
            return response()->json([
                'message' => 'Une transaction est déjà en cours.',
                'transaction' => [
                    'id'        => $transactionEnCours->id,
                    'reference' => $transactionEnCours->reference,
                    'montant'   => $transactionEnCours->montant,
                ]
            ], 422);
        }

        $reference = $this->genererReference($paiement);

        $transaction = Transaction::create([
            'paiement_id'      => $paiement->id,
            'mode_paiement'    => $validated['operateur'],
            'montant'          => $paiement->montant_attendu,
            'statut'           => 'en_attente',
            'reference'        => $reference,
            'telephone_payeur' => $validated['telephone'] ?? null,
            'ip_address'       => $request->ip(),
            'date_transaction' => now(),
        ]);

        Log::info("💳 Transaction créée", [
            'id' => $transaction->id,
            'reference' => $transaction->reference,
            'montant' => $transaction->montant,
        ]);

        try {
            $paymentData = match ($validated['operateur']) {
                'paypal' => $this->initierPaiementPayPal($transaction),
                'wave', 'orange_money', 'free_money' => $this->initierPaiementPaydunya($transaction),
            };
        } catch (\Exception $e) {
            Log::error("❌ Erreur initiation paiement : " . $e->getMessage());
            $transaction->update(['statut' => 'rejete']);
            return response()->json(['message' => 'Erreur lors de l\'initiation. Réessayez.'], 500);
        }

        // ✅ Recharger la transaction pour avoir reference_externe
        $transaction->refresh();

        Log::info("✅ Paiement initié avec succès", [
            'transaction_id' => $transaction->id,
            'reference_externe' => $transaction->reference_externe,
        ]);

        return response()->json([
            'success'      => true,
            'message'      => 'Paiement initié avec succès.',
            'transaction'  => [
                'id'        => $transaction->id,
                'reference' => $transaction->reference,
                'reference_externe' => $transaction->reference_externe, // ✅ AJOUTÉ
                'montant'   => $transaction->montant,
                'operateur' => $transaction->mode_paiement,
                'statut'    => $transaction->statut,
            ],
            'payment_data' => $paymentData,
        ]);
    }

    /**
     * ═══════════════════════════════════════════════════════════════
     * CONSULTATION DES TRANSACTIONS
     * ═══════════════════════════════════════════════════════════════
     */

    public function index(Request $request)
    {
        $user = $request->user();
        $locataire_id = $user->locataire->id ?? null;

        if (!$locataire_id) {
            return response()->json(['message' => 'Non autorisé.'], 403);
        }

        $transactions = Transaction::with('paiement.bail.logement')
            ->whereHas('paiement', function ($query) use ($locataire_id) {
                $query->where('locataire_id', $locataire_id);
            })
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'transactions' => $transactions->map(function ($transaction) {
                return [
                    'id' => $transaction->id,
                    'reference' => $transaction->reference,
                    'mode_paiement' => $transaction->mode_paiement,
                    'montant' => $transaction->montant,
                    'statut' => $transaction->statut,
                    'date_transaction' => $transaction->date_transaction,
                    'paiement' => [
                        'id' => $transaction->paiement->id,
                        'type' => $transaction->paiement->type,
                        'periode' => $transaction->paiement->periode,
                    ],
                ];
            })
        ]);
    }

    public function show($id, Request $request)
    {
        $user = $request->user();
        $locataire_id = $user->locataire->id ?? null;

        if (!$locataire_id) {
            return response()->json(['message' => 'Non autorisé.'], 403);
        }

        $transaction = Transaction::with('paiement.bail.logement')
            ->whereHas('paiement', function ($query) use ($locataire_id) {
                $query->where('locataire_id', $locataire_id);
            })
            ->findOrFail($id);

        return response()->json([
            'transaction' => [
                'id' => $transaction->id,
                'reference' => $transaction->reference,
                'mode_paiement' => $transaction->mode_paiement,
                'montant' => $transaction->montant,
                'statut' => $transaction->statut,
                'date_transaction' => $transaction->date_transaction,
                'telephone_payeur' => $transaction->telephone_payeur,
                'ip_address' => $transaction->ip_address,
            ],
            'paiement' => [
                'id' => $transaction->paiement->id,
                'type' => $transaction->paiement->type,
                'periode' => $transaction->paiement->periode,
                'montant_attendu' => $transaction->paiement->montant_attendu,
                'statut' => $transaction->paiement->statut,
            ],
            'bail' => [
                'id' => $transaction->paiement->bail->id,
                'logement' => $transaction->paiement->bail->logement->numero ?? null,
            ],
        ]);
    }

    public function verifierStatut($id, Request $request)
    {
        $user = $request->user();
        $locataire_id = $user->locataire->id ?? null;

        if (!$locataire_id) {
            return response()->json(['message' => 'Non autorisé.'], 403);
        }

        $transaction = Transaction::whereHas('paiement', function ($query) use ($locataire_id) {
            $query->where('locataire_id', $locataire_id);
        })->findOrFail($id);

        return response()->json([
            'transaction_id' => $transaction->id,
            'statut' => $transaction->statut,
            'reference' => $transaction->reference,
            'date_transaction' => $transaction->date_transaction,
            'paiement_statut' => $transaction->paiement->statut,
        ]);
    }

    /**
     * ═══════════════════════════════════════════════════════════════
     * GESTION DES TRANSACTIONS
     * ═══════════════════════════════════════════════════════════════
     */

    public function annuler($id, Request $request)
    {
        $user = $request->user();
        $locataire_id = $user->locataire->id ?? null;

        if (!$locataire_id) {
            return response()->json(['message' => 'Non autorisé.'], 403);
        }

        $transaction = Transaction::whereHas('paiement', function ($query) use ($locataire_id) {
            $query->where('locataire_id', $locataire_id);
        })->findOrFail($id);

        if ($transaction->statut !== 'en_attente') {
            return response()->json([
                'message' => 'Seules les transactions en attente peuvent être annulées.'
            ], 422);
        }

        $transaction->update(['statut' => 'rejete']);

        Log::info("🚫 Transaction {$id} annulée par le locataire");

        return response()->json([
            'success' => true,
            'message' => 'Transaction annulée avec succès.'
        ]);
    }

    public function relancer($id, Request $request)
    {
        $user = $request->user();
        $locataire_id = $user->locataire->id ?? null;

        if (!$locataire_id) {
            return response()->json(['message' => 'Non autorisé.'], 403);
        }

        $ancienneTransaction = Transaction::whereHas('paiement', function ($query) use ($locataire_id) {
            $query->where('locataire_id', $locataire_id);
        })->findOrFail($id);

        if ($ancienneTransaction->statut !== 'rejete') {
            return response()->json([
                'message' => 'Seules les transactions échouées peuvent être relancées.'
            ], 422);
        }

        $nouvelleReference = $this->genererReference($ancienneTransaction->paiement);

        $nouvelleTransaction = Transaction::create([
            'paiement_id'      => $ancienneTransaction->paiement_id,
            'mode_paiement'    => $ancienneTransaction->mode_paiement,
            'montant'          => $ancienneTransaction->montant,
            'statut'           => 'en_attente',
            'reference'        => $nouvelleReference,
            'telephone_payeur' => $ancienneTransaction->telephone_payeur,
            'ip_address'       => $request->ip(),
            'date_transaction' => now(),
        ]);

        try {
            $paymentData = match ($nouvelleTransaction->mode_paiement) {
                'paypal' => $this->initierPaiementPayPal($nouvelleTransaction),
                default  => $this->initierPaiementPaydunya($nouvelleTransaction),
            };
        } catch (\Exception $e) {
            Log::error("❌ Erreur relance paiement : " . $e->getMessage());
            $nouvelleTransaction->update(['statut' => 'rejete']);
            return response()->json(['message' => 'Erreur lors de la relance. Réessayez.'], 500);
        }

        Log::info("🔄 Transaction relancée : Ancienne {$id}, Nouvelle {$nouvelleTransaction->id}");

        return response()->json([
            'success'     => true,
            'message'     => 'Nouvelle transaction créée.',
            'transaction' => [
                'id'        => $nouvelleTransaction->id,
                'reference' => $nouvelleTransaction->reference,
                'montant'   => $nouvelleTransaction->montant,
            ],
            'payment_data' => $paymentData,
        ]);
    }

    /**
     * ═══════════════════════════════════════════════════════════════
     * MÉTHODES PRIVÉES (Intégration Mobile Money)
     * ═══════════════════════════════════════════════════════════════
     */

    private function genererReference(Paiement $paiement): string
    {
        $type       = $paiement->type === 'signature' ? 'BAIL' : 'LOYER';
        $bailId     = $paiement->bail_id;
        $paiementId = $paiement->id;
        $unique     = strtoupper(substr(uniqid(), -6));

        return "{$type}-{$bailId}-{$paiementId}-{$unique}";
    }

    private function initierPaiementPaydunya(Transaction $transaction): array
    {
        $mode    = config('services.paydunya.mode', 'test');
        $baseUrl = $mode === 'live'
            ? 'https://app.paydunya.com/api/v1'
            : 'https://app.paydunya.com/sandbox-api/v1';

        $paiement = $transaction->paiement;

        Log::info("📞 Appel API PayDunya", [
            'transaction_id' => $transaction->id,
            'montant' => $transaction->montant,
        ]);

        $response = Http::withHeaders([
            'PAYDUNYA-MASTER-KEY'  => config('services.paydunya.master_key'),
            'PAYDUNYA-PRIVATE-KEY' => config('services.paydunya.private_key'),
            'PAYDUNYA-TOKEN'       => config('services.paydunya.token'),
            'Content-Type'         => 'application/json',
        ])->post("{$baseUrl}/checkout-invoice/create", [
            'invoice' => [
                'total_amount' => (int) $transaction->montant,
                'description'  => "Paiement {$paiement->type} - {$paiement->periode}",
            ],
            'store' => [
                'name'    => 'Luwaas',
                'tagline' => 'Bail ' . $paiement->bail_id,
            ],
            'actions' => [
                'cancel_url'   => config('app.url') . '/paiement/annule',
                'return_url'   => config('app.url') . '/paiement/succes',
                'callback_url' => config('app.url') . '/api/webhook/paydunya',
            ],
            'custom_data' => [
                'reference'      => $transaction->reference,
                'transaction_id' => $transaction->id,
            ],
        ]);

        if (!$response->successful() || !isset($response['response_code']) || $response['response_code'] !== '00') {
            Log::error("❌ Erreur PayDunya", $response->json());
            throw new \Exception("Erreur PayDunya : " . ($response['response_text'] ?? 'Inconnue'));
        }

        $token = $response->json('token');
        $paymentUrl = $response->json('response_text');

        Log::info("✅ Réponse PayDunya reçue", [
            'token' => $token,
            'payment_url' => $paymentUrl,
        ]);

        // ✅✅✅ CRITIQUE : STOCKER LE TOKEN
        $transaction->update([
            'reference_externe' => $token,
        ]);

        Log::info("✅ reference_externe stockée", [
            'transaction_id' => $transaction->id,
            'reference_externe' => $token,
        ]);

        return [
            'payment_url' => $paymentUrl,
            'token'       => $token,
        ];
    }

    private function initierPaiementPayPal(Transaction $transaction): array
    {


        Log::info("💳 Initiation paiement PayPal pour transaction {$transaction->id}");

        // TODO: Implémenter l'API PayPal
        return [
            'payment_url' => 'https://www.sandbox.paypal.com/checkoutnow?token=xxx',
            'order_id' => 'paypal_order_xxx',
        ];
    }

    // ❌ MÉTHODE webhookPaydunya() SUPPRIMÉE
    // Les webhooks sont gérés dans WebhookController
}
