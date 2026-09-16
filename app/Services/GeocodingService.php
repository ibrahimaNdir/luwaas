<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeocodingService
{
    protected $apiUrl;
    protected $format = 'json';
    protected $limit = 1;

    public function __construct()
    {
        $this->apiUrl = config('services.geocoding.api_url', 'https://nominatim.openstreetmap.org/search');
    }

    /**
     * Geocode an address and return latitude and longitude.
     *
     * @param string $address The address to geocode (should include city/country for better accuracy)
     * @return array|null ['latitude' => float, 'longitude' => float] or null if failed
     */
    public function geocodeAddress(string $address): ?array
    {
        if (empty($address)) {
            return null;
        }

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

        return null;
    }
}