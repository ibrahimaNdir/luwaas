<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray($request)
    {
        return [
            'id'        => $this->id,
            'prenom'    => $this->prenom,
            'nom'       => $this->nom,
            'email'     => $this->email,
            'telephone' => $this->telephone,
            'user_type' => $this->user_type,
            'is_active' => $this->is_active,
        ];
    }

}
