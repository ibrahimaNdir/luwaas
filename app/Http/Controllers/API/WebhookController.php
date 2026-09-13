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

        if ($path === '/webhook/bictorys') {
            $gatewayKey = 'bictorys';
        } elseif ($path === '/webhook/paydunya') {
            $gatewayKey = 'paydunya';
        }

        Log::info("📩 IPN {$gatewayKey} reçu", $request->all());

        // Créer une instance du service webhook spécifique au gateway
        $webhookService = new WebhookService(
            app(AppServicesBailService::class),
            app(AppServicesLandlordEarningsService::class),
            app(AppServicesPayoutProcessorService::class),
            app(AppServicesGatewayResolver::class),
            $gatewayKey
        );
        $result = $webhookService->handle($request);

        $status = $result['status'] ?? 200;

        unset($result['status']);

        return response()->json($result, $status);
    }
}