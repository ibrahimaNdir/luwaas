<?php

namespace App\Contracts;

interface SmsProviderInterface
{
    /**
     * Envoie un SMS à un destinataire donné.
     *
     * @param string $to Numéro de téléphone
     * @param string $message Contenu du SMS
     * @return bool Succès ou échec
     */
    public function send(string $to, string $message): bool;
}
