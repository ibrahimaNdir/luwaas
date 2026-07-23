<?php

namespace App\Services\Sms;

use App\Contracts\SmsDriverInterface;
use Illuminate\Support\Facades\Log;

class FakeSmsDriver implements SmsDriverInterface
{
    /**
     * Stocke les messages envoyés pour les assertions de tests.
     */
    public array $messages = [];

    public function send(string $recipient, string $message): bool
    {
        $this->messages[] = [
            'recipient' => $recipient,
            'message'   => $message,
        ];

        Log::info("[FakeSmsDriver] SMS simulé envoyé à $recipient: $message");

        return true;
    }

    /**
     * Permet de vérifier dans un test si un SMS a été envoyé.
     */
    public function assertSentTo(string $recipient): bool
    {
        foreach ($this->messages as $msg) {
            if ($msg['recipient'] === $recipient) {
                return true;
            }
        }
        return false;
    }
}
