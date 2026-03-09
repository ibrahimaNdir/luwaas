<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\Bail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf;

class WebhookController extends Controller
{
    /**
     * ═══════════════════════════════════════════════════════════════
     * WEBHOOK PAYPAL
     * ═══════════════════════════════════════════════════════════════
     */
    public function handlePaypal(Request $request)
    {
        Log::info("💳 Webhook PayPal reçu", $request->all());

        // 1. Vérifier la signature
        if (!$this->verifierSignaturePaypal($request)) {
            Log::warning("⚠️ Signature PayPal invalide");
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        $eventType = $request->input('event_type');
        $resource = $request->input('resource', []);
        $customId = $resource['custom_id'] ?? null;

        // 2. Trouver la transaction
        $transaction = Transaction::with('paiement.bail')->where('reference', $customId)->first();

        if (!$transaction) {
            Log::error("❌ Transaction introuvable pour référence {$customId}");
            return response()->json(['error' => 'Transaction not found'], 404);
        }

        $paiement = $transaction->paiement;
        $bail = $paiement->bail;

        // 3. Gérer chaque événement
        switch ($eventType) {

            case 'PAYMENT.CAPTURE.COMPLETED':
                // Vérifier doublon
                if ($transaction->statut !== 'en_attente') {
                    Log::info("⚠️ Transaction déjà traitée : {$transaction->id}");
                    return response()->json(['success' => true, 'message' => 'Already processed']);
                }

                // ✅ Vérification du montant (sécurité)
                $montantPayPal = (float) ($resource['amount']['value'] ?? 0);
                $montantAttendu = (float) ($transaction->montant / 600); // Conversion FCFA → USD

                if (abs($montantPayPal - $montantAttendu) > 0.01) { // Tolérance 1 centime
                    Log::error("❌ Montant incorrect - attendu: {$montantAttendu} USD, reçu: {$montantPayPal} USD");
                    return response()->json(['error' => 'Invalid amount'], 400);
                }

                // Mettre à jour transaction
                $transaction->update([
                    'reference_externe' => $resource['id'] ?? null,
                    'statut' => 'valide',
                    'ip_address' => $request->ip(),
                    'date_transaction' => now(),
                ]);

                // Mettre à jour paiement
                $paiement->update([
                    'montant_paye' => $transaction->montant,
                    'montant_restant' => 0,
                    'statut' => 'payé',
                    'date_paiement' => now(),
                ]);

                // ✅ SI C'EST UN PAIEMENT DE SIGNATURE → ACTIVER LE BAIL
                if ($paiement->type === 'signature') {
                    $this->activerBail($bail, $transaction);
                }

                Log::info("✅ Paiement PayPal complété - bail {$bail->id}");
                break;

            case 'PAYMENT.CAPTURE.REFUNDED':
            case 'PAYMENT.CAPTURE.REVERSED':
                $transaction->update(['statut' => 'rembourse']);
                $paiement->update([
                    'montant_paye' => 0,
                    'montant_restant' => $paiement->montant_attendu,
                    'statut' => 'impayé'
                ]);

                // Si c'était le paiement signature, désactiver le bail
                if ($paiement->type === 'signature' && $bail->statut === 'actif') {
                    $bail->update(['statut' => 'resilie']);
                    $bail->logement->update(['statut_occupe' => 'disponible']);
                }

                Log::info("🔄 Paiement PayPal remboursé - bail {$bail->id}");
                break;

            case 'PAYMENT.CAPTURE.DENIED':
            case 'PAYMENT.CAPTURE.DECLINED':
                $transaction->update(['statut' => 'rejete']);

                Log::info("❌ Paiement PayPal refusé - transaction {$transaction->id}");
                break;

            default:
                Log::info("⏳ Événement PayPal ignoré : {$eventType}");
                return response()->json(['success' => true, 'message' => 'Event ignored']);
        }

        return response()->json([
            'success' => true,
            'message' => 'Événement traité avec succès',
        ]);
    }

    /**
     * ═══════════════════════════════════════════════════════════════
     * WEBHOOK PAYDUNYA (Wave, Orange Money, Free Money)
     * ═══════════════════════════════════════════════════════════════
     */
    public function handlePaydunya(Request $request)
    {
        Log::info("💰 IPN PayDunya reçu", $request->all());

        // 1. Vérifier la signature IPN
        if (!$this->verifierSignaturePaydunya($request)) {
            Log::warning("⚠️ Signature PayDunya invalide");
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        // 2. Extraire les données ✅ CORRIGÉ ICI
        $token = $request->input('data.invoice.token')
            ?? $request->input('data.hash')
            ?? $request->input('data.token')
            ?? $request->input('token');

        $statut = $request->input('data.status')
            ?? $request->input('status');

        $montantRecu = $request->input('data.invoice.total_amount')
            ?? $request->input('data.total_amount')
            ?? $request->input('total_amount');

        if (!$token) {
            Log::error("❌ Token manquant dans IPN PayDunya");
            return response()->json(['error' => 'Missing token'], 400);
        }

        // 3. Trouver la transaction
        $transaction = Transaction::with('paiement.bail')
            ->where('reference_externe', $token)
            ->first();

        if (!$transaction) {
            Log::error("❌ Transaction introuvable pour token {$token}");
            return response()->json(['error' => 'Transaction not found'], 404);
        }

        // 4. Vérification doublon
        if ($transaction->statut !== 'en_attente') {
            Log::info("⚠️ Transaction déjà traitée : {$token}");
            return response()->json(['success' => true, 'message' => 'Already processed']);
        }

        // 5. Vérification du montant (sécurité anti-fraude)
        if ((float) $transaction->montant !== (float) $montantRecu) {
            Log::error("❌ Montant incorrect - attendu: {$transaction->montant}, reçu: {$montantRecu}");
            return response()->json(['error' => 'Invalid amount'], 400);
        }

        $paiement = $transaction->paiement;
        $bail = $paiement->bail;

        // 6. Gérer chaque statut
        switch ($statut) {

            case 'completed':
                // Mettre à jour transaction
                $transaction->update([
                    'statut' => 'valide',
                    'ip_address' => $request->ip(),
                    'date_transaction' => now(),
                ]);

                // Mettre à jour paiement
                $paiement->update([
                    'montant_paye' => $transaction->montant,
                    'montant_restant' => 0,
                    'statut' => 'payé',
                    'date_paiement' => now(),
                ]);

                // ✅ SI C'EST UN PAIEMENT DE SIGNATURE → ACTIVER LE BAIL
                if ($paiement->type === 'signature') {
                    $this->activerBail($bail, $transaction);
                }

                Log::info("✅ Paiement PayDunya complété - bail {$bail->id}");
                break;

            case 'cancelled':
                $transaction->update(['statut' => 'rejete']);
                Log::info("❌ Paiement PayDunya annulé - transaction {$transaction->id}");
                break;

            case 'pending':
                Log::info("⏳ Paiement PayDunya en attente - token {$token}");
                return response()->json(['success' => true, 'message' => 'Payment pending']);

            default:
                Log::warning("⚠️ Statut PayDunya inconnu : {$statut}");
                return response()->json(['success' => true, 'message' => 'Unknown status']);
        }

        return response()->json([
            'success' => true,
            'message' => 'Paiement traité avec succès',
        ]);
    }

    /**
     * ═══════════════════════════════════════════════════════════════
     * ACTIVER LE BAIL (Appelé après paiement signature validé)
     * ═══════════════════════════════════════════════════════════════
     */
    private function activerBail(Bail $bail, Transaction $transaction)
    {
        Log::info("🔄 Activation du bail {$bail->id} via transaction {$transaction->id}");

        if ($bail->statut === 'actif') {
            Log::warning("⚠️ Bail {$bail->id} déjà actif");
            return;
        }

        // 1. Mettre à jour le bail
        $bail->update([
            'statut' => 'actif',
            'date_activation' => now(),
        ]);

        // 2. Générer les loyers mensuels
        $this->genererPaiementsMensuels($bail);

        // 3. Marquer le logement comme loué
        $bail->logement->update(['statut_occupe' => 'loue']);

        // 4. Mettre à jour la demande si existe
        if ($bail->demande_id) {
            \App\Models\Demande::find($bail->demande_id)->update(['statut' => 'bail_signe']);
        }

        // 5. Générer le PDF ✅ PASSER LA TRANSACTION ICI
        $this->genererBailPDF($bail, $transaction);

        // 6. Envoyer notifications
        event(new \App\Events\BailSigne($bail));

        Log::info("✅ Bail {$bail->id} activé avec succès");
    }

    private function genererBailPDF(Bail $bail, Transaction $transaction)
    {
        try {
            $bail->load(['locataire.user', 'logement.propriete.proprietaire.user']);

            // ✅ Passer BAIL ET TRANSACTION au template
            $pdf = PDF::loadView('bail_pdf', compact('bail', 'transaction'));

            $filename = "bail_{$bail->id}_" . now()->format('Ymd_His') . ".pdf";
            $path = "baux/{$filename}";

            Storage::disk('public')->put($path, $pdf->output());

            $bail->update(['document_pdf_path' => $path]);

            Log::info("📄 PDF généré : {$path}");
        } catch (\Exception $e) {
            Log::error("❌ Erreur génération PDF : " . $e->getMessage());
        }
    }

    /**
     * Générer les loyers mensuels
     */
    private function genererPaiementsMensuels(Bail $bail)
    {
        // ✅ Vérification doublon génération
        $dejaCree = \App\Models\Paiement::where('bail_id', $bail->id)
            ->where('type', 'loyer_mensuel')
            ->exists();

        if ($dejaCree) {
            Log::warning("⚠️ Paiements mensuels déjà générés pour bail {$bail->id}");
            return;
        }

        $start = \Carbon\Carbon::parse($bail->date_debut);
        $end = \Carbon\Carbon::parse($bail->date_fin);
        $current = $start->copy()->addMonth(); // Premier loyer : 1 mois après début

        Log::info("📅 Génération des paiements mensuels pour bail {$bail->id}");

        while ($current <= $end) {
            $jour = min($bail->jour_echeance, $current->copy()->endOfMonth()->day);
            $dateEcheance = $current->copy()->day($jour);
            $periode = $current->isoFormat('MMMM YYYY');

            \App\Models\Paiement::create([
                'locataire_id' => $bail->locataire_id,
                'bail_id' => $bail->id,
                'type' => 'loyer_mensuel',
                'montant_attendu' => $bail->montant_loyer + $bail->charges_mensuelles,
                'montant_paye' => 0,
                'montant_restant' => $bail->montant_loyer + $bail->charges_mensuelles,
                'statut' => 'impayé',
                'date_echeance' => $dateEcheance,
                'periode' => $periode,
            ]);

            $current->addMonth();
        }

        Log::info("✅ Paiements mensuels générés pour bail {$bail->id}");
    }

    /**
     * ═══════════════════════════════════════════════════════════════
     * VÉRIFICATION SIGNATURES
     * ═══════════════════════════════════════════════════════════════
     */

    private function verifierSignaturePaypal(Request $request): bool
    {
        // ⚠️ DÉSACTIVER EN LOCAL POUR TESTER
        if (config('app.env') === 'local') {
            Log::info("🧪 Mode TEST : Vérification signature PayPal désactivée");
            return true;
        }

        try {
            $headers = [
                'auth_algo' => $request->header('PAYPAL-AUTH-ALGO'),
                'cert_url' => $request->header('PAYPAL-CERT-URL'),
                'transmission_id' => $request->header('PAYPAL-TRANSMISSION-ID'),
                'transmission_sig' => $request->header('PAYPAL-TRANSMISSION-SIG'),
                'transmission_time' => $request->header('PAYPAL-TRANSMISSION-TIME'),
            ];

            $webhookId = config('services.paypal.webhook_id');
            $body = $request->getContent();

            $client = new \GuzzleHttp\Client();
            $tokenUrl = config('services.paypal.sandbox')
                ? 'https://api-m.sandbox.paypal.com/v1/oauth2/token'
                : 'https://api-m.paypal.com/v1/oauth2/token';

            $tokenResponse = $client->post($tokenUrl, [
                'auth' => [
                    config('services.paypal.client_id'),
                    config('services.paypal.secret'),
                ],
                'form_params' => ['grant_type' => 'client_credentials'],
            ]);

            $accessToken = json_decode($tokenResponse->getBody(), true)['access_token'];

            $verifyUrl = config('services.paypal.sandbox')
                ? 'https://api-m.sandbox.paypal.com/v1/notifications/verify-webhook-signature'
                : 'https://api-m.paypal.com/v1/notifications/verify-webhook-signature';

            $verifyResponse = $client->post($verifyUrl, [
                'headers' => [
                    'Authorization' => "Bearer {$accessToken}",
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'auth_algo' => $headers['auth_algo'],
                    'cert_url' => $headers['cert_url'],
                    'transmission_id' => $headers['transmission_id'],
                    'transmission_sig' => $headers['transmission_sig'],
                    'transmission_time' => $headers['transmission_time'],
                    'webhook_id' => $webhookId,
                    'webhook_event' => json_decode($body, true),
                ],
            ]);

            $result = json_decode($verifyResponse->getBody(), true);

            return ($result['verification_status'] ?? '') === 'SUCCESS';
        } catch (\Exception $e) {
            Log::error("❌ Erreur vérification signature PayPal : " . $e->getMessage());
            return false;
        }
    }

    private function verifierSignaturePaydunya(Request $request): bool
    {
        // ⚠️ DÉSACTIVER EN LOCAL POUR TESTER
        if (config('app.env') === 'local') {
            Log::info("🧪 Mode TEST : Vérification signature PayDunya désactivée");
            return true;
        }

        try {
            $token = config('services.paydunya.token');
            $masterKey = config('services.paydunya.master_key');

            if (!$token || !$masterKey) {
                Log::error("❌ Clés PayDunya manquantes dans config");
                return false;
            }

            $receivedToken = $request->header('X-PayDunya-Token');

            if (!$receivedToken) {
                Log::warning("⚠️ Header X-PayDunya-Token manquant");
                return false;
            }

            return hash_equals($token, $receivedToken);
        } catch (\Exception $e) {
            Log::error("❌ Erreur vérification signature PayDunya : " . $e->getMessage());
            return false;
        }
    }
}
