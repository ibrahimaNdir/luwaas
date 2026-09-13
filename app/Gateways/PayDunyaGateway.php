<?php

namespace App\Gateways;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;

class PayDunyaGateway implements PaymentGatewayInterface
{
    /**
     * Process a pay-in transaction (receiving funds from customer).
     *
     * @param float $amount       Amount to pay
     * @param string $phone       Customer phone number (E.164 format)
     * @param string $reference   Internal transaction reference
     * @param string $channel     Payment channel (wave, orange_money, etc.)
     * @return array              Result with keys: success (bool), token (string|null), payment_url (string|null), error (string|null)
     */
    public function processPayin(float $amount, string $phone, string $reference, string $channel): array
    {
        try {
            $mode    = config('services.paydunya.mode', 'test');
            $baseUrl = $mode === 'live'
                ? 'https://app.paydunya.com/api/v1'
                : 'https://app.paydunya.com/sandbox-api/v1';

            $response = Http::withHeaders([
                'PAYDUNYA-MASTER-KEY'  => config('services.paydunya.master_key'),
                'PAYDUNYA-PRIVATE-KEY' => config('services.paydunya.private_key'),
                'PAYDUNYA-TOKEN'       => config('services.paydunya.token'),
                'Content-Type'         => 'application/json',
            ])->post("{$baseUrl}/checkout-invoice/create", [
                'invoice' => [
                    'total_amount' => (int) $amount,
                    'description'  => "Paiement Luwaas",
                ],
                'store' => [
                    'name'    => 'Luwaas',
                    'tagline' => 'Luwaas',
                ],
                'actions' => [
                    'cancel_url'   => config('app.url') . '/paiement/annule',
                    'return_url'   => config('app.url') . '/paiement/succes',
                    'callback_url' => config('app.url') . '/api/webhook/paydunya',
                ],
                'custom_data' => [
                    'transaction_id' => 0, // Will be set later by the caller
                    'reference'      => $reference,
                    'type'           => 'payin',
                ],
            ]);

            if (!$response->successful() || ($response['response_code'] ?? null) !== '00') {
                Log::error("❌ Erreur PayDunya", $response->json());
                return [
                    'success' => false,
                    'token' => null,
                    'payment_url' => null,
                    'error' => 'Erreur PayDunya : ' . ($response['response_text'] ?? 'Inconnue'),
                ];
            }

            $token = $response->json('token');
            $lienPaiement = $response->json('response_text'); // This is the payment URL

            return [
                'success' => true,
                'token' => $token,
                'payment_url' => $lienPaiement,
                'error' => null,
            ];
        } catch (\Exception $e) {
            Log::error("❌ Erreur PayDunya gateway: " . $e->getMessage());
            return [
                'success' => false,
                'token' => null,
                'payment_url' => null,
                'error' => 'Exception lors de l appel à l API PayDunya: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Process a payout transaction (sending funds to customer).
     * Utilise l'API de déboursement de PayDunya.
     *
     * @param float $amount       Amount to pay out
     * @param string $phone       Customer phone number (E.164 format)
     * @param string $reference   Internal payout reference
     * @param string $channel     Payout channel (wave, orange_money, free_money, bank_transfer)
     * @return array              Result with keys: success (bool), external_reference (string|null), error (string|null)
     */
    public function processPayout(float $amount, string $phone, string $reference, string $channel): array
    {
        try {
            $mode    = config('services.paydunya.mode', 'test');
            $baseUrl = $mode === 'live'
                ? 'https://app.paydunya.com/api/v1'
                : 'https://app.paydunya.com/sandbox-api/v1';

            // Mapper les canaux Luwaas vers les codes PayDunya (à ajuster selon leur docs)
            $channelMap = [
                'orange_money' => 'om',
                'free_money'   => 'fm',
                'wave'         => 'wave',
                'bank_transfer'=> 'bank',
            ];
            $paydunyaChannel = $channelMap[$channel] ?? $channel;

            $response = Http::withHeaders([
                'PAYDUNYA-MASTER-KEY'  => config('services.paydunya.master_key'),
                'PAYDUNYA-PRIVATE-KEY' => config('services.paydunya.private_key'),
                'PAYDUNYA-TOKEN'       => config('services.paydunya.token'),
                'Content-Type'         => 'application/json',
            ])->post("{$baseUrl}/disbursement/create", [
                'amount' => (int) $amount, // PayDunya attend généralement des entiers (XOF sans décimales)
                'currency' => 'XOF',
                'external_id' => $reference,
                'phone_number' => $phone, // Doit déjà être en E.164 (+221xxxxxxxx)
                'narration' => 'Paiement loyer Luwaas',
                'channel' => $paydunyaChannel,
            ]);

            if ($response->successful() && isset($response['response_code']) && $response['response_code'] === '00') {
                $result = $response->json();

                return [
                    'success' => true,
                    'external_reference' => $result['transaction_id'] ?? $result['id'] ?? null,
                    'error' => null,
                ];
            } else {
                $errorMsg = $response['response_text'] ?? 'Erreur inconnue';
                Log::error("❌ Erreur PayDunya Payout", [
                    'response' => $response->json(),
                    'amount' => $amount,
                    'phone' => $phone,
                    'reference' => $reference,
                    'channel' => $channel,
                ]);

                return [
                    'success' => false,
                    'external_reference' => null,
                    'error' => 'Erreur PayDunya : ' . $errorMsg,
                ];
            }
        } catch (\Exception $e) {
            Log::error("❌ Exception PayDunya Payout: " . $e->getMessage(), [
                'amount' => $amount,
                'phone' => $phone,
                'reference' => $reference,
                'channel' => $channel,
            ]);

            return [
                'success' => false,
                'external_reference' => null,
                'error' => 'Exception PayDunya : ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Get the gateway name.
     *
     * @return string Gateway identifier
     */
    public function getName(): string
    {
        return 'paydunya';
    }

    /**
     * Verify the authenticity of an incoming PayDunya webhook.
     *
     * @param  Request $request  The HTTP webhook request
     * @return bool              true if the signature is valid
     */
    public function verifyWebhook(Request $request): bool
    {
        $masterKey = config('services.paydunya.master_key');

        if (!$masterKey) {
            Log::error('PayDunya master key missing in configuration');
            return false;
        }

        $expectedHash = hash('sha512', $masterKey);

        // PayDunya sends the key in the PAYDUNYA-MASTER-KEY header
        $headerHash = $request->header('PAYDUNYA-MASTER-KEY');

        if ($headerHash && hash_equals($expectedHash, $headerHash)) {
            return true;
        }

        // Also check in payload as fallback (some webhook formats)
        $payloadHash = $request->input('data.hash');
        if ($payloadHash && hash_equals($expectedHash, $payloadHash)) {
            return true;
        }

        return false;
    }

    /**
     * Normalize the PayDunya webhook payload into a standard format.
     *
     * @param  Request $request
     * @return array [
     *     'token'           => string,
     *     'status'          => string,
     *     'amount'          => float,
     *     'transaction_ref' => string,
     * ]
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

        // Normalize PayDunya statuses to standard Luwaas statuses
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
}