<?php

namespace App\Services;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Cache;

class SystemConfigService
{
    /**
     * Récupère une configuration système
     */
    public function get(string $key, $default = null)
    {
        return Cache::remember("config_{$key}", 3600, function () use ($key, $default) {
            $setting = SystemSetting::where('key', $key)->first();
            return $setting ? $setting->value : $default;
        });
    }

    /**
     * Définit ou met à jour une configuration
     */
    public function set(string $key, $value): void
    {
        SystemSetting::updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget("config_{$key}");
    }

    // Valeurs par défaut métier
    // Paramètres économiques
    public function getBasePrice(string $tier): float
    {
        return (float) $this->get("price_base_{$tier}", 2000); // 2000 par défaut pour starter
    }

    public function getPricePerProperty(): float
    {
        return (float) $this->get('price_per_property', 6000); // 6000 par bien
    }

    /**
     * Récupère le taux de commission pour un opérateur donné
     */
    /**
     * Récupère le taux de commission pour un opérateur donné
     */
    public function getRateForOperator(string $operator): float
    {
        $rate = \App\Models\CommissionRate::where('operator', $operator)
            ->where('valid_from', '<=', now())
            ->where(function($query) {
                $query->whereNull('valid_to')->orWhere('valid_to', '>=', now());
            })
            ->first();

        return $rate ? (float)$rate->rate_percent : 0.035; // 3.5% par défaut si pas trouvé
    }

    public function getPlatformFixedFee(): float
    {
        return (float) $this->get('commission_fixe_luwaas', 1000); // 1000 FCFA par défaut
    }
}
