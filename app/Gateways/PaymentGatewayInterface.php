<?php

namespace App\Gateways;

/**
 * Interface for payment gateway implementations.
 */
interface PaymentGatewayInterface
{
    /**
     * Process a pay-in transaction (receiving funds from customer).
     *
     * @param float $amount       Amount to pay
     * @param string $phone       Customer phone number (E.164 format)
     * @param string $reference   Internal transaction reference
     * @param string $channel     Payment channel (wave, orange_money, etc.)
     * @return array              Result with keys: success (bool), token (string|null), payment_url (string|null), error (string|null)
     */
    public function processPayin(float $amount, string $phone, string $reference, string $channel): array;

    /**
     * Process a payout transaction (sending funds to customer).
     *
     * @param float $amount       Amount to pay out
     * @param string $phone       Customer phone number (E.164 format)
     * @param string $reference   Internal payout reference
     * @param string $channel     Payout channel (wave, orange_money, free_money, bank_transfer)
     * @return array              Result with keys: success (bool), external_reference (string|null), error (string|null)
     */
    public function processPayout(float $amount, string $phone, string $reference, string $channel): array;

    /**
     * Get the gateway name.
     *
     * @return string Gateway identifier (e.g., 'paydunya', 'bictorys')
     */
    public function getName(): string;
}