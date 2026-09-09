<?php

namespace App\Contracts;

use App\Models\Transaction;
use Illuminate\Http\Request;

/**
 * Contrat unique pour tout agrégateur de paiement.
 *
 * Pour ajouter un nouvel agrégateur :
 *   1. Créer app/Services/Gateways/MonNouvelAgregateurGateway.php
 *   2. Implémenter toutes les méthodes de cette interface
 *   3. Dans AppServiceProvider, changer la liaison IoC (1 ligne)
 *
 * Aucun autre fichier ne change. ✅
 */
interface PaymentGatewayInterface
{
    /**
     * Créer une invoice de paiement chez l'agrégateur.
     *
     * @param  Transaction $transaction  La transaction locale (déjà créée en DB)
     * @param  array       $options      description, store_tagline, cancel_url, return_url, callback_url
     * @return array                     ['token' => '...', 'payment_url' => '...', 'raw' => [...]]
     */
    public function initiateCheckout(Transaction $transaction, array $options): array;

    /**
     * Envoyer de l'argent directement sur le compte mobile d'un destinataire (reversement bailleur).
     *
     * @param  string $recipient  Numéro de téléphone ou alias du bailleur
     * @param  float  $amount     Montant net à reverser (déjà déduit de la commission Luwaas)
     * @return array              Réponse brute du gateway
     * @throws \Exception         Si le reversement échoue
     */
    public function directPayout(string $recipient, float $amount): array;

    /**
     * Vérifier l'authenticité d'un webhook entrant.
     *
     * @param  Request $request  La requête HTTP du webhook
     * @return bool              true si la signature est valide
     */
    public function verifyWebhook(Request $request): bool;

    /**
     * Normaliser le payload d'un webhook en un format standard,
     * indépendant de l'agrégateur.
     *
     * @param  Request $request
     * @return array [
     *     'token'           => string,   // identifiant unique de la transaction chez le gateway
     *     'status'          => string,   // 'completed' | 'failed' | 'cancelled' | 'pending'
     *     'amount'          => float,    // montant reçu
     *     'transaction_ref' => string,   // référence interne du gateway
     * ]
     */
    public function normalizeWebhookPayload(Request $request): array;

    /**
     * Récupérer le taux de commission PSP pour un opérateur donné.
     * Lu depuis la table commission_rates (pas hardcodé).
     *
     * @param  string $operator  wave | orange_money | free_money | card
     * @return float             Taux décimal (ex: 0.015 pour 1.5%)
     */
    public function getPspRate(string $operator): float;
}
