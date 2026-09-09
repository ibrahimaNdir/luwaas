<?php

namespace App\Observers;

use App\Models\Propriete;
use App\Services\GeocodingService;

class ProprieteObserver
{
    protected $geocoding;

    public function __construct(GeocodingService $geocoding)
    {
        $this->geocoding = $geocoding;
    }

    /**
     * Handle the Propriete "saved" event.
     *
     * @param  \App\Models\Propriete  $propriete
     * @return void
     */
    public function saved(Propriete $propriete): void
        {
            // Only geocode if latitude or longitude missing and we have an address
            if ((empty($propriete->latitude) || empty($propriete->longitude)) && !empty($propriete->adresse)) {
                $coords = $this->geocoding->geocodeAddress($propriete->adresse);

                if ($coords) {
                    // Update without firing events again to avoid recursion
                    $propriete->withoutEvents(function () use ($propriete, $coords) {
                        $propriete->update([
                            'latitude'  => $coords['lat'],
                            'longitude' => $coords['lon'],
                        ]);
                    });
                }
            }
        }
}