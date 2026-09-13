<?php

namespace App\Gateways;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;

class BictorysGateway implements PaymentGatewayInterface
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
            // Récupérer la configuration Bictorys
            $apiUrl = config('services.bictorys.api_url');
            $apiKey = config('services.bictorys.api_key');
            $privateKey = config('services.bictorys.private_key');

            if (!$apiUrl || !$apiKey || !$privateKey) {
                return [
                    'success' => false,
                    'token' => null,
                    'payment_url' => null,
                    'error' => 'Configuration Bictorys manquante',
                ];
            }

            // Effectuer l'appel à l'API Bictorys pour le paiement entrant
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ])->post($apiUrl . '/v1/checkout/create', [ // Assuming this is the payin endpoint
                'amount' => (int) $amount,
                'currency' => 'XOF',
                'external_id' => $reference,
                'phone_number' => $phone,
                'narration' => 'Paiement loyer Luwaas',
                'channel' => $channel,
            ]);

            if ($response->successful()) {
                $result = $response->json();

                return [
                    'success' => true,
                    'token' => $result['token'] ?? null,
                    'payment_url' => $result['payment_url'] ?? $result['redirect_url'] ?? null,
                    'error' => null,
                ];
            } else {
                // Erreur de l'API
                $errorMessage = 'Erreur API Bictorys: ';
                if ($response->clientError()) {
                    $errorMessage .= 'Erreur client - ' . $response->body();
                } elseif ($response->serverError()) {
                    $errorMessage .= 'Erreur serveur - ' . $response->body();
                } else {
                    $errorMessage .= $response->body();
                }

                return [
                    'success' => false,
                    'token' => null,
                    'payment_url' => null,
                    'error' => $errorMessage,
                ];
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'token' => null,
                'payment_url' => null,
                'error' => 'Exception lors de l appel à l API Bictorys: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Process a payout transaction (sending funds to customer).
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
            // Format du numéro de téléphone (devrait déjà être en format E.164)
            $phoneNumber = $phone;

            // S'assurer que le numéro commence par le code pays du Sénégal (+221)
            if (!preg_match('/^\+221/', $phoneNumber)) {
                // Si le numéro commence par 0, remplacer par +221
                if (preg_match('/^0/', $phoneNumber)) {
                    $phoneNumber = '+221' . substr($phoneNumber, 1);
                }
                // Si le numéro commence par 221 mais sans +, ajouter le +
                elseif (preg_match('/^221/', $phoneNumber)) {
                    $phoneNumber = '+' . $phoneNumber;
                }
                // Sinon, ajouter +221 au début
                else {
                    $phoneNumber = '+221' . $phoneNumber;
                }
            }

            // Effectuer l'appel à l'API Bictorys pour le déboursement
            $apiUrl = config('services.bictorys.api_url');
            $apiKey = config('services.bictorys.api_key');
            $privateKey = config('services.bictorys.private_key');

            if (!$apiUrl || !$apiKey || !$privateKey) {
                return [
                    'success' => false,
                    'external_reference' => null,
                    'error' => 'Configuration Bictorys manquante',
                ];
            }

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ])->post($apiUrl . '/v1/disbursement', [
                'amount' => (float) $amount,
                'currency' => 'XOF',
                'external_id' => $reference,
                'phone_number' => $phoneNumber,
                'narration' => 'Paiement loyer Luwaas',
                'reference' => $reference,
            ]);

            if ($response->successful()) {
                $result = $response->json();

                return [
                    'success' => true,
                    'external_reference' => $result['transaction_id'] ?? $result['id'] ?? null,
                    'error' => null,
                ];
            } else {
                // Erreur de l'API
                $errorMessage = 'Erreur API Bictorys: ';
                if ($response->clientError()) {
                    $errorMessage .= 'Erreur client - ' . $response->body();
                } elseif ($response->serverError()) {
                    $errorMessage .= 'Erreur serveur - ' . $response->body();
                } else {
                    $errorMessage .= $response->body();
                }

                return [
                    'success' => false,
                    'external_reference' => null,
                    'error' => $errorMessage,
                ];
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'external_reference' => null,
                'error' => 'Exception lors de l appel à l API Bictorys: ' . $e->getMessage(),
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
        return 'bictorys';
    }

    /**
     * Verify the authenticity of an incoming Bictorys webhook.
     *
     * @param  Request $request  The HTTP webhook request
     * @return bool              true if the signature is valid
     */
    public function verifyWebhook(Request $request): bool
    {
        $webhookSecret = config('services.bictorys.webhook_secret');

        if (!$webhookSecret) {
            Log::error('Bictorys webhook secret missing in configuration');
            return false;
        }

        // Get the raw payload
        $payload = $request->getContent();
        if (!$payload) {
            Log::warning('Empty payload received for Bictorys webhook');
            return false;
        }

        // Get the signature from headers
        $signature = $request->header('BICTORYS-SIGNATURE');
        if (!$signature) {
            Log::warning('BICTORYS-SIGNATURE header missing in webhook request');
            return false;
        }

        // Compute HMAC-SHA256 of the payload using the webhook secret
        $expectedSignature = hash_hmac('sha256', $payload, $webhookSecret);

        // Compare signatures using hash_equals to prevent timing attacks
        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Normalize the Bictorys webhook payload into a standard format.
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
        $token = $request->input('data.token')
            ?? $request->input('transaction_token')
            ?? $request->input('token')
            ?? '';

        $rawStatus = $request->input('data.status')
            ?? $request->input('status')
            ?? '';

        // Normalize Bictorys statuses to standard Luwaas statuses
        // This mapping would need to be adjusted based on actual Bictorys status values
        $status = match (strtolower($rawStatus)) {
            'completed', 'success', 'settled' => 'completed',
            'cancelled', 'canceled'           => 'cancelled',
            'failed', 'error', 'declined'     => 'failed',
            'pending', 'processing'           => 'pending',
            default                           => $rawStatus,
        };

        $amount = (float) (
            $request->input('data.amount')
            ?? $request->input('transaction_amount')
            ?? $request->input('amount')
            ?? 0
        );

        $transactionRef = $request->input('data.transaction_id')
            ?? $request->input('external_id')
            ?? $request->input('reference')
            ?? $request->input('id')
            ?? $token;

        return [
            'token'           => $token,
            'status'          => $status,
            'amount'          => $amount,
            'transaction_ref' => $transactionRef,
        ];
    }
}