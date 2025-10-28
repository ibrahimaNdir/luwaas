<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LogementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check(); // Autorise uniquement les utilisateurs authentifiés
    }

    public function rules(): array
    {
        return [

            'numero'         => 'required|string|max:50',
            'superficie'     => 'nullable|numeric|min:0',
            'nombre_pieces'  => 'nullable|integer|min:0',
            'meuble'         => 'required|boolean',
            'etat'           => 'required|in:excellent,bon,moyen,renovation_requise',
            'type'           => 'required|in:studio,appartement,maison,villa',
            'description'    => 'nullable|string',
            'prix_loyer'     => 'required|numeric|min:0'
        ];
    }

    public function messages(): array
    {
        return [
            'propriete_id.required'  => 'La propriété est obligatoire.',
            'propriete_id.exists'    => 'La propriété sélectionnée est invalide.',
            'numero.required'        => 'Le numéro du logement est obligatoire.',
            'etat.in'                => 'L\'état doit être l\'un des suivants : excellent, bon, moyen ou renovation_requise.',
            'typelogement.in'        => 'Le type de logement doit être studio, appartement, maison ou villa.',
            'meuble.required'        => 'Le champ meuble est obligatoire (true ou false).'

        ];
    }
}
