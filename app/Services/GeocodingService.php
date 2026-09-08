<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
<<<<<<< HEAD
use Illuminate\Support\Facades\Log;
=======
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
>>>>>>> 9fbdc60 (Mise à jour économique, payouts, demandes, et ressources)

class GeocodingService
{
    protected $apiUrl;
<<<<<<< HEAD
    protected $format = 'json';
    protected $limit = 1;
=======
    protected $cacheMinutes;
>>>>>>> 9fbdc60 (Mise à jour économique, payouts, demandes, et ressources)

    public function __construct()
    {
        $this->apiUrl = config('services.geocoding.api_url', 'https://nominatim.openstreetmap.org/search');
<<<<<<< HEAD
    }

    /**
     * Geocode an address and return latitude and longitude.
     *
     * @param string $address The address to geocode (should include city/country for better accuracy)
     * @return array|null ['latitude' => float, 'longitude' => float] or null if failed
=======
        $this->cacheMinutes = config('services.geocoding.cache_minutes', 1440); // 24h default
    }

    /**
     * Geocode an address to latitude/longitude.
     *
     * @param string $address
     * @return array|null ['lat' => float, 'lon' => float] or null if failed
>>>>>>> 9fbdc60 (Mise à jour économique, payouts, demandes, et ressources)
     */
    public function geocodeAddress(string $address): ?array
    {
        if (empty($address)) {
            return null;
        }

<<<<<<< HEAD
        try {
            $response = Http::withHeaders([
                'User-Agent' => 'Luwaas/1.0 (https://luwaas.sn; contact@luwaas.sn)' // Replace with actual contact if needed
            ])->get($this->apiUrl, [
                'q' => $address . ', Sénégal',
                'format' => $this->format,
                'limit' => $this->limit,
                'addressdetails' => 1
            ]);

            if ($response->successful() && $response->json()) {
                $result = $response->json()[0];
                return [
                    'latitude'  => (float)$result['lat'],
                    'longitude' => (float)$result['lon']
                ];
            }
        } catch (\Exception $e) {
            Log::warning("Geocoding failed for address '{$address}': {$e->getMessage()}");
        }

=======
        $cacheKey = 'geocoding_' . md5($address);

        // Return cached result if exists
        if ($this->cacheMinutes > 0 && Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        try {
            $response = Http::get($this->apiUrl, [
                'q' => $address,
                'format' => 'json',
                'limit' => 1,
                'addressdetails' => 1,
            ]);

            if ($response->successful() && $response->count() > 0) {
                $data = $response->json()[0];
                $lat = (float) $data['lat'];
                $lon = (float) $data['lon'];

                $result = ['lat' => $lat, 'lon' => $lon];

                // Cache successful result
                if ($this->cacheMinutes > 0) {
                    Cache::put($cacheKey, $result, $this->cacheMinutes);
                }

                return $result;
            }
        } catch (\Exception $e) {
            // Log the error if needed; we just return null
            // \Log::warning("Geocoding failed for address {$address}: " . $e->getMessage());
        }

        // Cache miss (no result) – do not cache failures to allow retry
>>>>>>> 9fbdc60 (Mise à jour économique, payouts, demandes, et ressources)
        return null;
    }
}