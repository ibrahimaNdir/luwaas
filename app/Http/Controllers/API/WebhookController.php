<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Bail;
use App\Models\Paiement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
   
    public function handleWave(Request $request)
    {
        Log::info("🌊 Webhook Wave reçu", $request->all());

        // 1. Vérifier la signature du webhook (sécurité)
        if (!$this->verifierSignatureWave($request)) {
            Log::warning("⚠️ Signature Wave invalide");
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        // 2. Extraire les données
        $transactionId = $request->input('id'); // ID transaction Wave
        $status = $request->input('status'); // completed, failed, etc.
        $montant = $request->input('amount');
        $telephone = $request->input('client_phone');
        $metadata = $request->input('metadata', []); // bail_id stocké ici

        // 3. Vérifier que le paiement est réussi
        if ($status !== 'completed') {
            Log::info("⏳ Paiement Wave non complété : {$status}");
            return response()->json(['success' => true, 'message' => 'Payment pending']);
        }

        // 4. Récupérer le bail depuis les metadata
        $bailId = $metadata['bail_id'] ?? null;

        if (!$bailId) {
            Log::error("❌ Bail ID manquant dans metadata Wave");
            return response()->json(['error' => 'Missing bail_id'], 400);
        }

        // 5. Activer le bail
        try {
            $bailController = new BailController();
            $bailController->activerBail($bailId, [
                'transaction_id' => $transactionId,
                'montant' => $montant,
                'telephone' => $telephone,
                'operateur' => 'wave',
                'ip' => $request->ip(),
            ]);

            Log::info("✅ Bail {$bailId} activé via Wave");

            return response()->json([
                'success' => true,
                'message' => 'Bail activated successfully'
            ]);
        } catch (\Exception $e) {
            Log::error("❌ Erreur activation bail Wave : " . $e->getMessage());
            return response()->json(['error' => 'Activation failed'], 500);
        }
    }

  
    public function handleOrangeMoney(Request $request)
    {
        Log::info("Orange Money reçu", $request->all());

        // Logique similaire à Wave
        // Adapter selon la documentation Orange Money API

        $transactionId = $request->input('txnid');
        $status = $request->input('status');
        $montant = $request->input('amount');
        $telephone = $request->input('msisdn');

        // Récupérer bail_id depuis votre système de référence
        $reference = $request->input('reference'); // Vous stockez bail_id ici
        $bailId = $this->extraireBailIdDeReference($reference);

        if ($status !== 'SUCCESS') {
            Log::info("⏳ Paiement OM non réussi : {$status}");
            return response()->json(['status' => 'pending']);
        }

        try {
            $bailController = new BailController();
            $bailController->activerBail($bailId, [
                'transaction_id' => $transactionId,
                'montant' => $montant,
                'telephone' => $telephone,
                'operateur' => 'orange_money',
                'ip' => $request->ip(),
            ]);

            Log::info("✅ Bail {$bailId} activé via Orange Money");

            return response()->json(['status' => 'success']);
        } catch (\Exception $e) {
            Log::error("❌ Erreur activation bail OM : " . $e->getMessage());
            return response()->json(['status' => 'error'], 500);
        }
    }

   
    public function handleFreeMoney(Request $request)
    {
        Log::info("Free Money reçu", $request->all());

        // Logique similaire aux autres
        // Adapter selon la documentation Free Money API

        $transactionId = $request->input('transaction_id');
        $status = $request->input('status');
        $montant = $request->input('amount');
        $telephone = $request->input('phone');
        $bailId = $request->input('bail_id'); // Selon votre implémentation

        if ($status !== 'completed') {
            Log::info("⏳ Paiement Free Money non complété : {$status}");
            return response()->json(['success' => false]);
        }

        try {
            $bailController = new BailController();
            $bailController->activerBail($bailId, [
                'transaction_id' => $transactionId,
                'montant' => $montant,
                'telephone' => $telephone,
                'operateur' => 'free_money',
                'ip' => $request->ip(),
            ]);

            Log::info("✅ Bail {$bailId} activé via Free Money");

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            Log::error("❌ Erreur activation bail Free Money : " . $e->getMessage());
            return response()->json(['success' => false], 500);
        }
    }

    /**
     * ═══════════════════════════════════════════════════════════════
     * MÉTHODES UTILITAIRES
     * ═══════════════════════════════════════════════════════════════
     */

    /**
     * Vérifier la signature du webhook Wave
     */
    private function verifierSignatureWave(Request $request)
    {
        // À implémenter selon la doc Wave
        // Exemple :
        $signature = $request->header('X-Wave-Signature');
        $secret = config('services.wave.webhook_secret');

        $expectedSignature = hash_hmac('sha256', $request->getContent(), $secret);

        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Extraire le bail_id d'une référence
     */
    private function extraireBailIdDeReference($reference)
    {
        // Format: BAIL-{bail_id}-{timestamp}
        // Exemple: BAIL-123-1677012345
        $parts = explode('-', $reference);
        return $parts[1] ?? null;
    }


    
    public function handlePaypal(Request $request)
    {
        Log::info(" Webhook PayPal reçu", $request->all());

        // 1. Vérifier la signature du webhook PayPal
        if (!$this->verifierSignaturePaypal($request)) {
            Log::warning("⚠️ Signature PayPal invalide");
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        // 2. Extraire le type d'événement
        $eventType = $request->input('event_type');

        // 3. On traite uniquement les paiements complétés
        if ($eventType !== 'PAYMENT.CAPTURE.COMPLETED') {
            Log::info("⏳ Événement PayPal ignoré : {$eventType}");
            return response()->json(['success' => true, 'message' => 'Event ignored']);
        }

        // 4. Extraire les données de la ressource
        $resource      = $request->input('resource', []);
        $transactionId = $resource['id'] ?? null;
        $montant       = $resource['amount']['value'] ?? null;
        $devise        = $resource['amount']['currency_code'] ?? 'XOF';
        $metadata      = $resource['custom_id'] ?? null; // bail_id stocké dans custom_id

        // 5. Récupérer le bail_id
        $bailId = $metadata; // ou parser si vous y stockez d'autres infos

        if (!$bailId) {
            Log::error("❌ Bail ID manquant dans custom_id PayPal");
            return response()->json(['error' => 'Missing bail_id'], 400);
        }

        // 6. Activer le bail
        try {
            $bailController = new BailController();
            $bailController->activerBail($bailId, [
                'transaction_id' => $transactionId,
                'montant'        => $montant,
                'devise'         => $devise,
                'telephone'      => null, // PayPal ne fournit pas de téléphone
                'operateur'      => 'paypal',
                'ip'             => $request->ip(),
            ]);

            Log::info("✅ Bail {$bailId} activé via PayPal");

            return response()->json([
                'success' => true,
                'message' => 'Bail activated successfully'
            ]);
        } catch (\Exception $e) {
            Log::error("❌ Erreur activation bail PayPal : " . $e->getMessage());
            return response()->json(['error' => 'Activation failed'], 500);
        }
    }

    /**
     * Vérifier la signature du webhook PayPal
     */
    private function verifierSignaturePaypal(Request $request): bool
    {
        // PayPal envoie ces headers pour la vérification
        $headers = [
            'auth_algo'         => $request->header('PAYPAL-AUTH-ALGO'),
            'cert_url'          => $request->header('PAYPAL-CERT-URL'),
            'transmission_id'   => $request->header('PAYPAL-TRANSMISSION-ID'),
            'transmission_sig'  => $request->header('PAYPAL-TRANSMISSION-SIG'),
            'transmission_time' => $request->header('PAYPAL-TRANSMISSION-TIME'),
        ];

        $webhookId = config('services.paypal.webhook_id');
        $body      = $request->getContent();

        // Appel à l'API PayPal pour valider la signature
        $client   = new \GuzzleHttp\Client();
        $tokenUrl = config('services.paypal.sandbox')
            ? 'https://api-m.sandbox.paypal.com/v1/oauth2/token'
            : 'https://api-m.paypal.com/v1/oauth2/token';

        // Récupérer le token d'accès
        $tokenResponse = $client->post($tokenUrl, [
            'auth'        => [
                config('services.paypal.client_id'),
                config('services.paypal.secret'),
            ],
            'form_params' => ['grant_type' => 'client_credentials'],
        ]);

        $accessToken = json_decode($tokenResponse->getBody(), true)['access_token'];

        // Vérifier la signature via l'API PayPal
        $verifyUrl = config('services.paypal.sandbox')
            ? 'https://api-m.sandbox.paypal.com/v1/notifications/verify-webhook-signature'
            : 'https://api-m.paypal.com/v1/notifications/verify-webhook-signature';

        $verifyResponse = $client->post($verifyUrl, [
            'headers' => [
                'Authorization' => "Bearer {$accessToken}",
                'Content-Type'  => 'application/json',
            ],
            'json' => [
                'auth_algo'         => $headers['auth_algo'],
                'cert_url'          => $headers['cert_url'],
                'transmission_id'   => $headers['transmission_id'],
                'transmission_sig'  => $headers['transmission_sig'],
                'transmission_time' => $headers['transmission_time'],
                'webhook_id'        => $webhookId,
                'webhook_event'     => json_decode($body, true),
            ],
        ]);

        $result = json_decode($verifyResponse->getBody(), true);

        return ($result['verification_status'] ?? '') === 'SUCCESS';
    }
}
