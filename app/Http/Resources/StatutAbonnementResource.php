<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StatutAbonnementResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Assuming $this->resource is an array from subscriptionSummary()
        // We'll just return it as is, but we can structure it if needed.
        // For now, we assume it's already an array with keys like 'status', 'plan', etc.
        return $this->resource;
    }
}