<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\WebhookService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    public function __construct(protected WebhookService $webhookService) {}

    /**
     * Point d'entrée unique pour tous les webhooks PayDunya.
     * Route: POST /api/webhook/paydunya
     */
    public function handle(Request $request)
    {
        Log::info("📩 IPN PayDunya reçu", $request->all());

        if (!$this->webhookService->verifierSignature($request)) {
            Log::warning("⚠️ Signature PayDunya invalide");
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        $result = $this->webhookService->handle($request);

        $status = $result['status'] ?? 200;

        unset($result['status']);

        return response()->json($result, $status);
    }
}