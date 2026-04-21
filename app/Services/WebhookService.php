<?php

namespace App\Services;

use App\Models\Bail;
use App\Models\Paiement;
use App\Models\Transaction;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class WebhookService
{
    // ═══════════════════════════════════════════
    // VÉRIFICATION DES SIGNATURES
    // ═══════════════════════════════════════════

    public function verifierSignaturePaypal(Request $request): bool
    {
        if (config('app.env') === 'local') {
            Log::info("🧪 Mode TEST : Vérification signature PayPal désactivée");
            return true;
        }

        try {
            $accessToken = $this->getPaypalAccessToken();

            $verifyUrl = config('services.paypal.sandbox')
                ? 'https://api-m.sandbox.paypal.com/v1/notifications/verify-webhook-signature'
                : 'https://api-m.paypal.com/v1/notifications/verify-webhook-signature';

            $client   = new Client();
            $response = $client->post($verifyUrl, [
                'headers' => [
                    'Authorization' => "Bearer {$accessToken}",
                    'Content-Type'  => 'application/json',
                ],
                'json' => [
                    'auth_algo'       => $request->header('PAYPAL-AUTH-ALGO'),
                    'cert_url'        => $request->header('PAYPAL-CERT-URL'),
                    'transmission_id' => $request->header('PAYPAL-TRANSMISSION-ID'),
                    'transmission_sig' => $request->header('PAYPAL-TRANSMISSION-SIG'),
                    'transmission_time' => $request->header('PAYPAL-TRANSMISSION-TIME'),
                    'webhook_id'      => config('services.paypal.webhook_id'),
                    'webhook_event'   => json_decode($request->getContent(), true),
                ],
            ]);

            $result = json_decode($response->getBody(), true);

            return ($result['verification_status'] ?? '') === 'SUCCESS';
        } catch (\Exception $e) {
            Log::error("❌ Erreur vérification signature PayPal : " . $e->getMessage());
            return false;
        }
    }

    public function verifierSignaturePaydunya(Request $request): bool
    {
        if (config('app.env') === 'local') {
            Log::info("🧪 Mode TEST : Vérification signature PayDunya désactivée");
            return true;
        }

        try {
            $token    = config('services.paydunya.token');
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

    // ═══════════════════════════════════════════
    // TRAITEMENT COMMUN APRÈS PAIEMENT VALIDÉ
    // ═══════════════════════════════════════════

    public function validerPaiement(Transaction $transaction, Paiement $paiement, ?string $referenceExterne = null): void
    {
        $transaction->update([
            'reference_externe' => $referenceExterne ?? $transaction->reference_externe,
            'statut'            => 'valide',
            'ip_address'        => request()->ip(),
            'date_transaction'  => now(),
        ]);

        $paiement->update([
            'montant_paye'    => $transaction->montant,
            'montant_restant' => 0,
            'statut'          => 'payé',
            'date_paiement'   => now(),
        ]);

        if ($paiement->type === 'signature') {
            $this->activerBail($paiement->bail, $transaction);
        }
    }

    public function rembourserPaiement(Transaction $transaction, Paiement $paiement, Bail $bail): void
    {
        $transaction->update(['statut' => 'rembourse']);

        $paiement->update([
            'montant_paye'    => 0,
            'montant_restant' => $paiement->montant_attendu,
            'statut'          => 'impayé',
        ]);

        if ($paiement->type === 'signature' && $bail->statut === 'actif') {
            $bail->update(['statut' => 'resilie']);
            $bail->logement->update(['statut_occupe' => 'disponible']);
        }

        Log::info("🔄 Paiement remboursé - bail {$bail->id}");
    }

    public function rejeterTransaction(Transaction $transaction): void
    {
        $transaction->update(['statut' => 'rejete']);
        Log::info("❌ Transaction {$transaction->id} rejetée");
    }

    // ═══════════════════════════════════════════
    // ACTIVATION DU BAIL
    // ═══════════════════════════════════════════

    public function activerBail(Bail $bail, Transaction $transaction): void
    {
        Log::info("🔄 Activation du bail {$bail->id} via transaction {$transaction->id}");

        if ($bail->statut === 'actif') {
            Log::warning("⚠️ Bail {$bail->id} déjà actif");
            return;
        }

        $bail->update([
            'statut'           => 'actif',
            'date_activation'  => now(),
        ]);

        $this->genererPaiementsMensuels($bail);

        $bail->logement->update(['statut_occupe' => 'loue']);

        if ($bail->demande_id) {
            \App\Models\Demande::find($bail->demande_id)?->update(['statut' => 'bail_signe']);
        }

        $this->genererBailPDF($bail, $transaction);

        event(new \App\Events\BailSigne($bail));

        Log::info("✅ Bail {$bail->id} activé avec succès");
    }

    // ═══════════════════════════════════════════
    // GÉNÉRATION PDF
    // ═══════════════════════════════════════════

    public function genererBailPDF(Bail $bail, Transaction $transaction): void
    {
        try {
            $bail->load(['locataire.user', 'logement.propriete.proprietaire.user']);

            $pdf      = PDF::loadView('bail_pdf', compact('bail', 'transaction'));
            $filename = "bail_{$bail->id}_" . now()->format('Ymd_His') . ".pdf";
            $path     = "baux/{$filename}";

            Storage::disk('public')->put($path, $pdf->output());

            $bail->update(['document_pdf_path' => $path]);

            Log::info("📄 PDF généré : {$path}");
        } catch (\Exception $e) {
            Log::error("❌ Erreur génération PDF : " . $e->getMessage());
        }
    }

    // ═══════════════════════════════════════════
    // GÉNÉRATION DES LOYERS MENSUELS
    // ═══════════════════════════════════════════

    public function genererPaiementsMensuels(Bail $bail): void
    {
        $dejaCree = Paiement::where('bail_id', $bail->id)
            ->where('type', 'loyer_mensuel')
            ->exists();

        if ($dejaCree) {
            Log::warning("⚠️ Paiements mensuels déjà générés pour bail {$bail->id}");
            return;
        }

        $current = Carbon::parse($bail->date_debut)->addMonth();
        $end     = Carbon::parse($bail->date_fin);
        $montant = $bail->montant_loyer + $bail->charges_mensuelles;

        Log::info("📅 Génération des paiements mensuels pour bail {$bail->id}");

        while ($current <= $end) {
            $jour         = min($bail->jour_echeance, $current->copy()->endOfMonth()->day);
            $dateEcheance = $current->copy()->day($jour);

            Paiement::create([
                'locataire_id'    => $bail->locataire_id,
                'bail_id'         => $bail->id,
                'type'            => 'loyer_mensuel',
                'montant_attendu' => $montant,
                'montant_paye'    => 0,
                'montant_restant' => $montant,
                'statut'          => 'impayé',
                'date_echeance'   => $dateEcheance,
                'periode'         => $current->isoFormat('MMMM YYYY'),
            ]);

            $current->addMonth();
        }

        Log::info("✅ Paiements mensuels générés pour bail {$bail->id}");
    }

    // ─── Privé ───────────────────────────────────────────────────

    private function getPaypalAccessToken(): string
    {
        $client  = new Client();
        $baseUrl = config('services.paypal.sandbox')
            ? 'https://api-m.sandbox.paypal.com'
            : 'https://api-m.paypal.com';

        $response = $client->post("{$baseUrl}/v1/oauth2/token", [
            'auth'        => [config('services.paypal.client_id'), config('services.paypal.secret')],
            'form_params' => ['grant_type' => 'client_credentials'],
        ]);

        return json_decode($response->getBody(), true)['access_token'];
    }
}