<?php

namespace App\Services;

class ReferenceGenerator
{
    /**
     * Génère une référence standardisée pour les transactions
     *
     * @param  string  $prefix  Préfixe de référence (ex: LOYER, SUB, PAYOUT)
     * @param  int     $id1     Premier identifiant
     * @param  int     $id2     Deuxième identifiant
     * @return string           Référence formatée
     */
    public static function genererReference(string $prefix, int $id1, int $id2): string
    {
        return strtoupper($prefix) . '-' . $id1 . '-' . $id2 . '-' . strtoupper(substr(uniqid(), -6));
    }
}