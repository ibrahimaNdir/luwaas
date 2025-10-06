<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProprieteRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check();

    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'titre'         => 'required|string|max:255',
            'type'          => 'required|string|max:100', // maison, immeuble, terrain...
            'description'   => 'nullable|string',
            // Localisation
            'region_id'     => 'required|exists:regions,id',
            'departement_id'=> 'required|exists:departements,id',
            'commune_id'    => 'required|exists:communes,id',

            //
        ];
    }
    public function messages(): array
    {
        return [
            'titre.required'          => 'Le titre de la propriété est obligatoire.',
            'type.required'           => 'Le type de la propriété est obligatoire.',
            'region_id.required'      => 'La région est obligatoire.',
            'region_id.exists'        => 'La région sélectionnée est invalide.',
            'departement_id.required' => 'Le département est obligatoire.',
            'departement_id.exists'   => 'Le département sélectionné est invalide.',
            'commune_id.required'     => 'La commune est obligatoire.',
            'commune_id.exists'       => 'La commune sélectionnée est invalide.',
        ];
    }
}
