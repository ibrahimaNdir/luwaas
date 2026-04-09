<?php

namespace App\Services\Otp;

use Illuminate\Support\Facades\Http;

class SmsOtpService implements OtpServiceInterface
{
    private string $apiUrl;
    private string $apiKey;

    public function __construct()
    {
        $this->apiUrl = config('services.sms.api_url');
        $this->apiKey = config('services.sms.api_key');
    }

    public function generateOtp(): string
    {
        return (string) random_int(100000, 999999);
    }

    public function sendOtp(string $recipient, string $otp): void
    {
        Http::post($this->apiUrl, [
            'api_key' => $this->apiKey,
            'phone'   => $recipient,
            'message' => "Votre code de vérification Luwaas est : {$otp}. Il est valable 10 minutes.",
        ]);
    }
}
