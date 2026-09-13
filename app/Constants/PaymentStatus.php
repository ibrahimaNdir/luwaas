<?php

namespace App\Constants;

class PaymentStatus
{
    // Transaction statuses
    const PENDING = 'en_attente';
    const VALID = 'valide';
    const FAILED = 'rejete';

    // Payment statuses
    const PAYMENT_PENDING = 'en_attente';
    const PAYMENT_PAID = 'payé';
    const PAYMENT_FAILED = 'échoué';

    // Subscription statuses
    const SUBSCRIPTION_PENDING = 'en_attente';
    const SUBSCRIPTION_ACTIVE = 'actif';
    const SUBSCRIPTION_FAILED = 'échoué';
    const SUBSCRIPTION_CANCELLED = 'annulé';
    const SUBSCRIPTION_RENEWED = 'renouvelé';

    // Payout statuses
    const PAYOUT_PENDING = 'en_attente';
    const PAYOUT_PROCESSING = 'en_cours';
    const PAYOUT_COMPLETED = 'terminé';
    const PAYOUT_FAILED = 'échoué';

    // Bail statuses
    const BAIL_PENDING = 'en_attente';
    const BAIL_ACTIVE = 'actif';
    const BAIL_TERMINATED = 'terminé';
}