<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBailRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'demande_id'                 => 'required|exists:demandes,id',
            'montant_loyer'              => 'required|integer|min:1000',
            'charges_mensuelles'         => 'required|integer|min:0',
            'nombre_mois_caution'        => 'required|integer|min:1|max:6',
            'date_debut'                 => 'required|date|after_or_equal:today',
            'date_fin'                   => 'required|date|after:date_debut',
            'jour_echeance'              => 'required|integer|min:1|max:31',
            'renouvellement_automatique' => 'required|boolean',
            'conditions_speciales'       => 'nullable|string|max:2000',
        ];
    }
}