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
            'propriete_id'   => 'required|exists:proprietes,id',
            'numero'         => 'required|string|max:50',
            'superficie'     => 'nullable|numeric|min:0',
            'nombre_pieces'  => 'nullable|integer|min:0',
            'meuble'         => 'required|boolean',
            'etat'           => 'required|in:excellent,bon,moyen,renovation_requise',
            'typelogement'   => 'required|in:studio,appartement,maison,villa',
            'description'    => 'nullable|string',
            'prix_indicatif' => 'nullable|numeric|min:0',
            'statut_occupe'  => 'required|in:disponible,occupe,reserve',
            'statut_publication' => 'required|in:brouillon,publie',
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
            'meuble.required'        => 'Le champ meuble est obligatoire (true ou false).',
            'statut_occupe.in'       => 'Le statut occupé doit être disponible, occupe, ou reserve.',
            'statut_publication.in'  => 'Le statut de publication doit être brouillon ou publie.',
        ];
    }
}
