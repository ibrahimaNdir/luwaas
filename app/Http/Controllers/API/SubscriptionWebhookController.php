<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\Subscription\SubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SubscriptionWebhookController extends Controller
{
    public function __construct(
        protected SubscriptionService $subscriptionService
    ) {}

    // ─────────────────────────────────────────
    // IPN PAYDUNYA — Reçoit la confirmation
    // ─────────────────────────────────────────

    public function handle(Request $request)
    {
        // 1. Logger le payload brut pour debug
        Log::info('PayDunya IPN reçu', $request->all());

        // 2. Extraire les données
        $data   = $request->input('data', []);
        $bill   = $data['bill'] ?? [];
        $status = $bill['status'] ?? null;
        $token  = $bill['token'] ?? null;
        $ref    = $bill['transaction_id'] ?? null;

        // 3. Vérifier que le token est présent
        if (! $token) {
            Log::error('PayDunya IPN: token manquant', $request->all());
            return response()->json(['message' => 'Token manquant.'], 400);
        }

        // 4. Traiter selon le statut PayDunya
        if ($status === 'completed') {
            $activated = $this->subscriptionService->activateSubscription($token, $ref);

            if (! $activated) {
                Log::error('PayDunya IPN: activation échouée pour token ' . $token);
                return response()->json(['message' => 'Activation échouée.'], 422);
            }

            Log::info('PayDunya IPN: abonnement activé pour token ' . $token);
            return response()->json(['message' => 'Abonnement activé.'], 200);
        }

        // 5. Paiement échoué ou annulé
        if (in_array($status, ['cancelled', 'failed'])) {
            Log::warning('PayDunya IPN: paiement ' . $status . ' pour token ' . $token);
            return response()->json(['message' => 'Paiement ' . $status . '.'], 200);
        }

        // 6. Statut inconnu
        Log::warning('PayDunya IPN: statut inconnu ' . $status);
        return response()->json(['message' => 'Statut non traité.'], 200);
    }
}