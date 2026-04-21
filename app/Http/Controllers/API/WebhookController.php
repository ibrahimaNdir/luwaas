<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Services\WebhookService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    public function __construct(protected WebhookService $webhookService) {}

    // ═══════════════════════════════════════════
    // WEBHOOK PAYPAL
    // ═══════════════════════════════════════════

    public function handlePaypal(Request $request)
    {
        Log::info("💳 Webhook PayPal reçu", $request->all());

        if (!$this->webhookService->verifierSignaturePaypal($request)) {
            Log::warning("⚠️ Signature PayPal invalide");
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        $eventType = $request->input('event_type');
        $resource  = $request->input('resource', []);
        $customId  = $resource['custom_id'] ?? null;

        $transaction = Transaction::with('paiement.bail')->where('reference', $customId)->first();

        if (!$transaction) {
            Log::error("❌ Transaction introuvable pour référence {$customId}");
            return response()->json(['error' => 'Transaction not found'], 404);
        }

        $paiement = $transaction->paiement;
        $bail     = $paiement->bail;

        switch ($eventType) {

            case 'PAYMENT.CAPTURE.COMPLETED':
                if ($transaction->statut !== 'en_attente') {
                    return response()->json(['success' => true, 'message' => 'Already processed']);
                }

                // Vérification montant (sécurité anti-fraude)
                $montantPayPal  = (float) ($resource['amount']['value'] ?? 0);
                $montantAttendu = (float) ($transaction->montant / 600); // FCFA → USD

                if (abs($montantPayPal - $montantAttendu) > 0.01) {
                    Log::error("❌ Montant incorrect PayPal - attendu: {$montantAttendu}, reçu: {$montantPayPal}");
                    return response()->json(['error' => 'Invalid amount'], 400);
                }

                $this->webhookService->validerPaiement($transaction, $paiement, $resource['id'] ?? null);
                Log::info("✅ Paiement PayPal complété - bail {$bail->id}");
                break;

            case 'PAYMENT.CAPTURE.REFUNDED':
            case 'PAYMENT.CAPTURE.REVERSED':
                $this->webhookService->rembourserPaiement($transaction, $paiement, $bail);
                break;

            case 'PAYMENT.CAPTURE.DENIED':
            case 'PAYMENT.CAPTURE.DECLINED':
                $this->webhookService->rejeterTransaction($transaction);
                break;

            default:
                Log::info("⏳ Événement PayPal ignoré : {$eventType}");
                return response()->json(['success' => true, 'message' => 'Event ignored']);
        }

        return response()->json(['success' => true, 'message' => 'Événement traité avec succès']);
    }

    // ═══════════════════════════════════════════
    // WEBHOOK PAYDUNYA
    // ═══════════════════════════════════════════

    public function handlePaydunya(Request $request)
    {
        Log::info("💰 IPN PayDunya reçu", $request->all());

        if (!$this->webhookService->verifierSignaturePaydunya($request)) {
            Log::warning("⚠️ Signature PayDunya invalide");
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        $token = $request->input('data.invoice.token')
            ?? $request->input('data.hash')
            ?? $request->input('data.token')
            ?? $request->input('token');

        $statut      = $request->input('data.status') ?? $request->input('status');
        $montantRecu = $request->input('data.invoice.total_amount')
            ?? $request->input('data.total_amount')
            ?? $request->input('total_amount');

        if (!$token) {
            Log::error("❌ Token manquant dans IPN PayDunya");
            return response()->json(['error' => 'Missing token'], 400);
        }

        $transaction = Transaction::with('paiement.bail')->where('reference_externe', $token)->first();

        if (!$transaction) {
            Log::error("❌ Transaction introuvable pour token {$token}");
            return response()->json(['error' => 'Transaction not found'], 404);
        }

        if ($transaction->statut !== 'en_attente') {
            return response()->json(['success' => true, 'message' => 'Already processed']);
        }

        if ((float) $transaction->montant !== (float) $montantRecu) {
            Log::error("❌ Montant incorrect PayDunya - attendu: {$transaction->montant}, reçu: {$montantRecu}");
            return response()->json(['error' => 'Invalid amount'], 400);
        }

        $paiement = $transaction->paiement;
        $bail     = $paiement->bail;

        switch ($statut) {

            case 'completed':
                $this->webhookService->validerPaiement($transaction, $paiement);
                Log::info("✅ Paiement PayDunya complété - bail {$bail->id}");
                break;

            case 'cancelled':
                $this->webhookService->rejeterTransaction($transaction);
                break;

            case 'pending':
                Log::info("⏳ Paiement PayDunya en attente - token {$token}");
                return response()->json(['success' => true, 'message' => 'Payment pending']);

            default:
                Log::warning("⚠️ Statut PayDunya inconnu : {$statut}");
                return response()->json(['success' => true, 'message' => 'Unknown status']);
        }

        return response()->json(['success' => true, 'message' => 'Paiement traité avec succès']);
    }
}