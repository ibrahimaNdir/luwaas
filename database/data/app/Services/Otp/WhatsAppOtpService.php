<?php
// app/Services/Otp/WhatsAppOtpService.php

namespace App\Services\Otp;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppOtpService
{
    private string $token;
    private string $phoneNumberId;
    private string $templateName;

    public function __construct()
    {
        $this->token         = config('services.whatsapp.token');
        $this->phoneNumberId = config('services.whatsapp.phone_number_id');
        $this->templateName  = config('services.whatsapp.otp_template');
    }

    // Génère un code OTP à 6 chiffres
    public function generateOtp(): string
    {
        return str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    // Envoie l'OTP via WhatsApp
    public function sendOtp(string $telephone, string $otp): bool
    {
        $phone = $this->formatPhone($telephone);

        $response = Http::withToken($this->token)
            ->post("https://graph.facebook.com/v19.0/{$this->phoneNumberId}/messages", [
                'messaging_product' => 'whatsapp',
                'to'                => $phone,
                'type'              => 'template',
                'template'          => [
                    'name'       => $this->templateName,
                    'language'   => ['code' => 'fr'],
                    'components' => [[
                        'type'       => 'body',
                        'parameters' => [[
                            'type' => 'text',
                            'text' => $otp
                        ]]
                    ]]
                ]
            ]);

        if ($response->failed()) {
            Log::error('WhatsApp OTP échoué', [
                'phone'    => $phone,
                'response' => $response->json()
            ]);
            return false;
        }

        return true;
    }

    // Formate le numéro sénégalais → format international
    private function formatPhone(string $phone): string
    {
        $phone = preg_replace('/[\s\-]/', '', $phone);

        if (str_starts_with($phone, '0')) {
            return '+221' . substr($phone, 1);
        }

        if (!str_starts_with($phone, '+')) {
            return '+221' . $phone;
        }

        return $phone;
    }
}