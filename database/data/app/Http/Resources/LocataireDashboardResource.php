<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LocataireDashboardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'demandes_count'         => $this->resource['demandes_count'],
            'baux_actifs_count'      => $this->resource['baux_actifs_count'],
            'paiements_en_attente'   => $this->resource['paiements_en_attente'],
        ];
    }
}