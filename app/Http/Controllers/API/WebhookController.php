<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\WebhookService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

class WebhookController extends Controller
{
    /**
     * Point d'entrée pour les webhooks des différents gateways.
     * Détermine le gateway à partir de l'URL et utilise le serviceapproprié.
     *
     * Routes:
     *   POST /api/webhook/paydunya
     *   POST /api/webhook/bictorys
     */
    public function handle(Request $request)
    {
        // Déterminer le gateway à partir de l'URL
        $path = $request->path();
        $gatewayKey = 'paydunya'; // Par défaut pour la compatibilité arrière

        $result = $this->webhookService->handle($request);
        if (str_contains($path, '/webhook/bictorys')) {
            $gatewayKey = 'bictorys';
        } elseif (str_contains($path, '/webhook/paydunya')) {
            $gatewayKey = 'paydunya';
        }

        Log::info("📩 IPN {$gatewayKey} reçu", $request->all());

        // Créer une instance du service webhook spécifique au gateway
        $webhookService = new WebhookService(
            app(\App\Services\BailService::class),
            app(\App\Services\LandlordEarningsService::class),
            app(\App\Services\PayoutProcessorService::class),
            $gatewayKey
        );

        if (!$webhookService->verifierSignature($request)) {
            Log::warning("⚠️ Signature {$gatewayKey} invalide");
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        $result = $webhookService->handle($request);

        $status = $result['status'] ?? 200;

        unset($result['status']);

        return response()->json($result, $status);
    }
}