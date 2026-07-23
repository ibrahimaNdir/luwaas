<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SmsService
{
    private string $apiUrl;
    private string $apiKey;

    public function __construct()
    {
        $this->apiUrl = config('services.sms.api_url', '');
        $this->apiKey = config('services.sms.api_key', '');
    }

    public function send(string $recipient, string $message): bool
    {
        if (empty($this->apiUrl) || empty($this->apiKey)) {
            Log::warning("SmsService: API URL ou API Key manquante. SMS non envoyé à $recipient.");
            return false;
        }

        try {
            $response = Http::post($this->apiUrl, [
                'api_key' => $this->apiKey,
                'phone'   => $recipient,
                'message' => $message,
            ]);

            if ($response->successful()) {
                Log::info("SMS envoyé à $recipient: $message");
                return true;
            } else {
                Log::error("Erreur lors de l'envoi du SMS à $recipient", ['response' => $response->body()]);
                return false;
            }
        } catch (\Exception $e) {
            Log::error("Exception lors de l'envoi du SMS à $recipient: " . $e->getMessage());
            return false;
        }
    }
}
