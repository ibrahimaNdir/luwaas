<?php

namespace App\Contracts;

interface SmsDriverInterface
{
    /**
     * Envoie un SMS à un destinataire.
     *
     * @param string $recipient Le numéro de téléphone du destinataire.
     * @param string $message Le contenu du message.
     * @return bool True si l'envoi a réussi, false sinon.
     */
    public function send(string $recipient, string $message): bool;
}
