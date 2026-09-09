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
}