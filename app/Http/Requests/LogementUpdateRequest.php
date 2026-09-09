<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LogementUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Ensure the logement belongs to the authenticated proprietor
        $proprieteId = $this->route('proprieteId');
        $logementId = $this->route('id');
        $logement = \App\Models\Logement::whereHas('propriete', fn($q) => $q->where('proprietaire_id', $this->user()->proprietaire->id))
            ->where('propriete_id', $proprieteId)
            ->where('id', $logementId)
            ->first();

        return $logement !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'superficie'            => 'sometimes|numeric|min:0',
            'nombre_pieces'         => 'sometimes|integer|min:0',
            'meuble'                => 'sometimes|boolean',
            'etat'                  => 'sometimes|in:bon,moyen,a_renover',
            'description'           => 'sometimes|nullable|string',
            'prix_loyer'            => 'sometimes|numeric|min:0',
            'nombre_chambres'       => 'sometimes|integer|min:0',
            'nombre_salles_de_bain' => 'sometimes|integer|min:0',
            //'statut_publication'    => 'sometimes|in:publie,brouillon',
        ];
    }
}