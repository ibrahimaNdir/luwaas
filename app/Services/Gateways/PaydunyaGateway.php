<?php

namespace App\Services\Gateways;

use App\Contracts\PaymentGatewayInterface;
use App\Models\CommissionRate;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Implémentation PayDunya du gateway de paiement.
 *
 * �� ⚠��️  TOUT le code spécifique à PayDunya est isolé ici.
 *     Pour passer à un autre agrégateur (Byctorys, CinetPay, etc.) :
 *
 *     1. Créer app/Services/Gateways/ByctorysGateway.php
 *     2. Implémenter PaymentGatewayInterface
 *     3. Dans AppServiceProvider, changer UNE SEULE LIGNE :
 *          $this->app->bind(PaymentGatewayInterface::class, ByctorysGateway::class);
 *
 * Aucune autre modification n'est nécessaire. � ✅
 */
class PaydunyaGateway implements PaymentGatewayInterface
{
    // �� ═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�══�═�═�═�═
    // CONFIGURATION
    // �� ═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═

    private function baseUrl(): string
    {
        $mode = config('services.paydunya.mode', 'test');
        return $mode === 'live'
            ? 'https://app.paydunya.com/api/v1'
            : 'https://app.paydunya.com/sandbox-api/v1';
    }

    private function headers(): array
    {
        return [
            'PAYDUNYA-MASTER-KEY'  => config('services.paydunya.master_key'),
            'PAYDUNYA-PRIVATE-KEY' => config('services.paydunya.private_key'),
            'PAYDUNYA-TOKEN'       => config('services.paydunya.token'),
            'Content-Type'         => 'application/json',
        ];
    }

    // �� ═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═
    // INITIER UN PAIEMENT (checkout invoice)
    // �� ═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═

    public function initiateCheckout(Transaction $transaction, array $options): array
    {
        $response = Http::withHeaders($this->headers())
            ->post($this->baseUrl() . '/checkout-invoice/create', [
                'invoice' => [
                    'total_amount' => (int) $transaction->montant,
                    'description'  => $options['description'] ?? 'Paiement Luwaas',
                ],
                'store' => [
                    'name'    => config('app.name', 'Luwaas'),
                    'tagline' => $options['store_tagline'] ?? 'Gestion locative',
                ],
                'actions' => [
                    'cancel_url'   => $options['cancel_url']   ?? config('app.url') . '/paiement/annule',
                    'return_url'   => $options['return_url']   ?? config('app.url') . '/paiement/succes',
                    'callback_url' => $options['callback_url'] ?? config('app.url') . '/api/webhook/payment',
                ],
                'custom_data' => $options['custom_data'] ?? [],
            ]);

        if (!$response->successful() || ($response['response_code'] ?? null) !== '00') {
            Log::error('��❌ PayDunya initiateCheckout failed', [
                'transaction_id' => $transaction->id,
                'response'       => $response->json(),
            ]);
            throw new \Exception('Erreur PayDunya : ' . ($response['response_text'] ?? 'Inconnue'));
        }

        $token      = $response->json('token');
        $paymentUrl = $response->json('response_text'); // PayDunya retourne l'URL dans response_text

        // Stocker le token sur la transaction pour le matching webhook
        $transaction->update(['gateway_token' => $token]);

        Log::info('��✅ PayDunya checkout créé', [
            'transaction_id' => $transaction->id,
            'token'          => $token,
        ]);

        return [
            'token'       => $token,
            'payment_url' => $paymentUrl,
            'raw'         => $response->json(),
        ];
    }

    // �� ═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═
    // REVERSEMENT BAILLEUR (PER — direct pay)
    // �� ═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═

    public function directPayout(string $recipient, float $amount): array
    {
        $response = Http::withHeaders($this->headers())
            ->post($this->baseUrl() . '/direct-pay/credit-account', [
                'account_alias' => $recipient,
                'amount'        => (int) $amount,
            ]);

        if (!$response->successful()) {
            $errorMessage = $response['response_text'] ?? 'Erreur inconnue';
            Log::error("��❌ PayDunya directPayout failed pour {$recipient}", [
                'montant'  => $amount,
                'response' => $response->json(),
            ]);
            throw new \Exception('Échec reversement bailleur : ' . $errorMessage);
        }

        Log::info("��✅ PayDunya PER réussi vers {$recipient}", ['montant' => $amount]);

        return $response->json();
    }

    // �� ═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═
    // VÉRIFICATION WEBHOOK
    // �� ═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═

    public function verifyWebhook(Request $request): bool
    {
        $masterKey = config('services.paydunya.master_key');

        if (!$masterKey) {
            Log::error('��❌ Clé PayDunya manquante dans la configuration.');
            return false;
        }

        $expectedHash = hash('sha512', $masterKey);

        // PayDunya peut envoyer la clé dans le header ou dans le payload
        $headerHash  = $request->header('PAYDUNYA-MASTER-KEY');
        $payloadHash = $request->input('data.hash');

        if ($headerHash && hash_equals($expectedHash, $headerHash)) {
            return true;
        }

        if ($payloadHash && hash_equals($expectedHash, $payloadHash)) {
            return true;
        }

        return false;
    }

    // �� ═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═
    // NORMALISATION PAYLOAD WEBHOOK
    // �� ═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═

    /**
     * Convertit le payload PayDunya en format standard Luwaas.
     * Le WebhookService utilise ce format normalisé — pas de code PayDunya dans WebhookService.
     */
    public function normalizeWebhookPayload(Request $request): array
    {
        $token = $request->input('data.invoice.token')
            ?? $request->input('data.token')
            ?? $request->input('token')
            ?? '';

        $rawStatus = $request->input('data.status')
            ?? $request->input('status')
            ?? '';

        // Normaliser les statuts PayDunya → statuts Luwaas standard
        $status = match (strtolower($rawStatus)) {
            'completed'  => 'completed',
            'cancelled'  => 'cancelled',
            'failed'     => 'failed',
            'pending'    => 'pending',
            default      => $rawStatus,
        };

        $amount = (float) (
            $request->input('data.invoice.total_amount')
            ?? $request->input('data.total_amount')
            ?? $request->input('total_amount')
            ?? 0
        );

        $transactionRef = $request->input('data.transaction_id')
            ?? $request->input('transaction_id')
            ?? $token;

        return [
            'token'           => $token,
            'status'          => $status,
            'amount'          => $amount,
            'transaction_ref' => $transactionRef,
        ];
    }

    // �� ═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═
    // TAUX PSP (lu depuis commission_rates DB)
    // �� ═�══�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═�═

    /**
     * Récupère le taux PSP depuis la table commission_rates.
     * Pas de hardcode — mettre à jour le seeder si les tarifs changent.
     *
     * @param string $operator  wave | orange_money | free_money | card
     * @return float            Taux décimal (ex: 0.015 pour 1.5%)
     */
    public function getPspRate(string $operator): float
    {
        $rate = CommissionRate::where('operator', $operator)
            ->whereDate('valid_from', '<=', today())
            ->where(function ($q) {
                $q->whereNull('valid_to')
                  ->orWhereDate('valid_to', '>=', today());
            })
            ->orderByDesc('valid_from')
            ->value('rate_percent');

        if ($rate === null) {
            Log::warning("��⚠��️ Taux PSP introuvable pour l'opérateur '{$operator}', fallback à 1.5%");
            return 0.0150; // fallback sécurisé
        }

        return (float) $rate;
    }
}