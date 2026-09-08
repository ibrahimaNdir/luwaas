<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PayDunya
{
    private function baseUrl(): string
    {
        // PayDunya utilise la même URL pour sandbox et live
        return 'https://app.paydunya.com/api/v1';
    }

    private function headers(): array
    {
        return [
            'PAYDUNYA-MASTER-KEY'  => config('services.paydunya.master_key'),
            'PAYDUNYA-PRIVATE-KEY' => config('services.paydunya.private_key'),
            'PAYDUNYA-TOKEN'       => config('services.paydunya.token'),
            'Content-Type'         => 'application/json',
        ];
    }

    public static function getBalance(): int
    {
        $self = new static;
        $response = Http::withHeaders($self->headers())
            ->get($self->baseUrl() . '/get_balance');

        if (!$response->successful()) {
            Log::error('PayDunya getBalance failed', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            throw new \Exception('Erreur PayDunya getBalance : ' . $response->body());
        }

        $data = $response->json();
        return (int) ($data['Balance SN'] ?? $data['balance'] ?? 0);
    }

    public static function directPayout(string $numero, int $montant): array
    {
        $self = new static;
        $payload = [
            'account_alias' => $numero,
            'amount'        => (int) $montant,
            'callback_url'  => config('app.url') . '/callback',
        ];

        $response = Http::withHeaders($self->headers())
            ->post($self->baseUrl() . '/direct-pay/credit-account', $payload);

        if (!$response->successful()) {
            Log::error('PayDunya directPayout failed', [
                'numero'  => $numero,
                'montant' => $montant,
                'status'  => $response->status(),
                'body'    => $response->body(),
            ]);
            throw new \Exception('Échec PayDunya directPayout : ' . $response->body());
        }

        return $response->json();
    }
}