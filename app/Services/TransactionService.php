<?php

namespace App\Services;

use App\Models\Paiement;
use App\Models\Transaction;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TransactionService
{
    /**
     * Vérifie qu'un paiement est éligible à une nouvelle transaction.
     * Retourne un message d'erreur ou null si tout est OK.
     */
    public function validerInitiation(Paiement $paiement, int $locataireId): ?array
    {
        if ($paiement->locataire_id !== $locataireId) {
            return ['message' => 'Ce paiement ne vous appartient pas.', 'status' => 403];
        }

        if ($paiement->statut === 'payé') {
            return ['message' => 'Ce paiement est déjà réglé.', 'status' => 422];
        }

        $enCours = Transaction::where('paiement_id', $paiement->id)
            ->where('statut', 'en_attente')
            ->first();

        if ($enCours) {
            return [
                'message' => 'Une transaction est déjà en cours.',
                'transaction' => [
                    'id'        => $enCours->id,
                    'reference' => $enCours->reference,
                    'montant'   => $enCours->montant,
                ],
                'status' => 422,
            ];
        }

        return null;
    }

    /**
     * Crée une transaction et appelle l'opérateur de paiement.
     * Retourne [$transaction, $paymentData].
     */
    public function creerEtInitier(Paiement $paiement, string $operateur, ?string $telephone, string $ip): array
    {
        $transaction = Transaction::create([
            'paiement_id'      => $paiement->id,
            'mode_paiement'    => $operateur,
            'montant'          => $paiement->montant_attendu,
            'statut'           => 'en_attente',
            'reference'        => $this->genererReference($paiement),
            'telephone_payeur' => $telephone,
            'ip_address'       => $ip,
            'date_transaction' => now(),
        ]);

        Log::info("💳 Transaction créée", [
            'id'        => $transaction->id,
            'reference' => $transaction->reference,
            'montant'   => $transaction->montant,
        ]);

        $paymentData = $this->appelerOperateur($transaction);

        $transaction->refresh();

        return [$transaction, $paymentData];
    }

    /**
     * Crée une nouvelle transaction depuis une ancienne (relance).
     */
    public function relancer(Transaction $ancienne, string $ip): array
    {
        $nouvelle = Transaction::create([
            'paiement_id'      => $ancienne->paiement_id,
            'mode_paiement'    => $ancienne->mode_paiement,
            'montant'          => $ancienne->montant,
            'statut'           => 'en_attente',
            'reference'        => $this->genererReference($ancienne->paiement),
            'telephone_payeur' => $ancienne->telephone_payeur,
            'ip_address'       => $ip,
            'date_transaction' => now(),
        ]);

        $paymentData = $this->appelerOperateur($nouvelle);

        Log::info("🔄 Transaction relancée : Ancienne {$ancienne->id}, Nouvelle {$nouvelle->id}");

        return [$nouvelle, $paymentData];
    }

    /**
     * Formate une transaction pour la liste (index).
     */
    public function formatPourListe(Transaction $t): array
    {
        return [
            'id'               => $t->id,
            'reference'        => $t->reference,
            'mode_paiement'    => $t->mode_paiement,
            'montant'          => $t->montant,
            'statut'           => $t->statut,
            'date_transaction' => $t->date_transaction,
            'paiement' => [
                'id'      => $t->paiement->id,
                'type'    => $t->paiement->type,
                'periode' => $t->paiement->periode,
            ],
        ];
    }

    /**
     * Formate une transaction pour le détail (show).
     */
    public function formatPourDetail(Transaction $t): array
    {
        return [
            'transaction' => [
                'id'               => $t->id,
                'reference'        => $t->reference,
                'mode_paiement'    => $t->mode_paiement,
                'montant'          => $t->montant,
                'statut'           => $t->statut,
                'date_transaction' => $t->date_transaction,
                'telephone_payeur' => $t->telephone_payeur,
                'ip_address'       => $t->ip_address,
            ],
            'paiement' => [
                'id'              => $t->paiement->id,
                'type'            => $t->paiement->type,
                'periode'         => $t->paiement->periode,
                'montant_attendu' => $t->paiement->montant_attendu,
                'statut'          => $t->paiement->statut,
            ],
            'bail' => [
                'id'      => $t->paiement->bail->id,
                'logement' => $t->paiement->bail->logement->numero ?? null,
            ],
        ];
    }

    // ─── Privés ──────────────────────────────────────────────────

    private function genererReference(Paiement $paiement): string
    {
        $type   = $paiement->type === 'signature' ? 'BAIL' : 'LOYER';
        $unique = strtoupper(substr(uniqid(), -6));

        return "{$type}-{$paiement->bail_id}-{$paiement->id}-{$unique}";
    }

    private function appelerOperateur(Transaction $transaction): array
    {
        return match ($transaction->mode_paiement) {
            'paypal' => $this->initierPayPal($transaction),
            default  => $this->initierPaydunya($transaction),
        };
    }

    private function initierPaydunya(Transaction $transaction): array
    {
        $mode    = config('services.paydunya.mode', 'test');
        $baseUrl = $mode === 'live'
            ? 'https://app.paydunya.com/api/v1'
            : 'https://app.paydunya.com/sandbox-api/v1';

        $paiement = $transaction->paiement;

        Log::info("📞 Appel API PayDunya", [
            'transaction_id' => $transaction->id,
            'montant'        => $transaction->montant,
        ]);

        $response = Http::withHeaders([
            'PAYDUNYA-MASTER-KEY'  => config('services.paydunya.master_key'),
            'PAYDUNYA-PRIVATE-KEY' => config('services.paydunya.private_key'),
            'PAYDUNYA-TOKEN'       => config('services.paydunya.token'),
            'Content-Type'         => 'application/json',
        ])->post("{$baseUrl}/checkout-invoice/create", [
            'invoice' => [
                'total_amount' => (int) $transaction->montant,
                'description'  => "Paiement {$paiement->type} - {$paiement->periode}",
            ],
            'store' => [
                'name'    => 'Luwaas',
                'tagline' => 'Bail ' . $paiement->bail_id,
            ],
            'actions' => [
                'cancel_url'   => config('app.url') . '/paiement/annule',
                'return_url'   => config('app.url') . '/paiement/succes',
                'callback_url' => config('app.url') . '/api/webhook/paydunya',
            ],
            'custom_data' => [
                'reference'      => $transaction->reference,
                'transaction_id' => $transaction->id,
            ],
        ]);

        if (!$response->successful() || ($response['response_code'] ?? null) !== '00') {
            Log::error("❌ Erreur PayDunya", $response->json());
            throw new \Exception("Erreur PayDunya : " . ($response['response_text'] ?? 'Inconnue'));
        }

        $token = $response->json('token');

        $transaction->update(['reference_externe' => $token]);

        Log::info("✅ PayDunya OK", ['transaction_id' => $transaction->id, 'token' => $token]);

        return [
            'payment_url' => $response->json('response_text'),
            'token'       => $token,
        ];
    }

    private function initierPayPal(Transaction $transaction): array
    {
        Log::info("💳 Initiation PayPal pour transaction {$transaction->id}");

        // TODO: Implémenter l'API PayPal réelle
        return [
            'payment_url' => 'https://www.sandbox.paypal.com/checkoutnow?token=xxx',
            'order_id'    => 'paypal_order_xxx',
        ];
    }
}