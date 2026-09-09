<?php

namespace App\Services;

use App\Gateways\PaymentGatewayInterface;
use App\Models\PlatformSetting;
use InvalidArgumentException;

class GatewayResolver
{
    /**
     * Get the currently active gateway for new operations.
     *
     * @return PaymentGatewayInterface
     */
    public function getActiveGateway(): PaymentGatewayInterface
    {
        $gatewayKey = PlatformSetting::getValue('active_payment_gateway') ?? 'paydunya';
        return $this->resolve($gatewayKey);
    }

    /**
     * Resolve a gateway implementation by its key.
     * This is used for existing operations where the gateway used is stored.
     *
     * @param string $gatewayKey Gateway key such as 'paydunya' or 'bictorys'
     * @return PaymentGatewayInterface
     * @throws InvalidArgumentException if the gateway key is unknown
     */
    public function resolve(string $gatewayKey): PaymentGatewayInterface
    {
        return match ($gatewayKey) {
            'paydunya' => app(\App\Gateways\PayDunyaGateway::class),
            'bictorys' => app(\App\Gateways\BictorysGateway::class),
            default => throw new InvalidArgumentException("Gateway inconnu: {$gatewayKey}")
        };
    }
}