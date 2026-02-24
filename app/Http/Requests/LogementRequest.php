<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\Propriete;

class LogementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        // Récupère le proprieteId depuis la route
        $proprieteId = $this->route('proprieteId');
        
        // Charge la propriété pour connaître son type
        $propriete = Propriete::find($proprieteId);
        
        // Détermine les types de logement autorisés
        $typesAutorises = $this->getTypesLogementAutorises($propriete);

        $numeroRule = ($propriete && strtolower($propriete->type) === 'immeuble')
        ? ['required', 'string', 'max:50', Rule::unique('logements', 'numero')->where('propriete_id', $proprieteId)]
        : ['nullable', 'string', 'max:50'];

        return [
            'numero'         => $numeroRule,
            'superficie'     => 'nullable|numeric|min:0',
            'nombre_pieces'  => 'nullable|integer|min:0',
            'meuble'         => 'required|boolean',
            'etat'           => 'required|string',
            'typelogement'   => [
                'required',
                'string',
                Rule::in($typesAutorises)
            ],
            'description'    => 'nullable|string',
            'prix_loyer'     => 'required|numeric|min:0',
            'nombre_chambres' => 'required|integer|min:0',
            'nombre_salles_de_bain' => 'required|integer|min:0',
        ];
    }  

    /**
     * Retourne les types de logement autorisés selon le type de propriété
     */
    private function getTypesLogementAutorises(?Propriete $propriete): array
    {
        if (!$propriete) {
            return []; // Aucun type autorisé si propriété introuvable
        }

        return match(strtolower($propriete->type)) {
            'villa' => ['villa'],
            'maison' => ['maison'],
            'immeuble' => ['studio', 'appartement'],
            default => [] // Type de propriété inconnu
        };
    }

    /**
     * Messages d'erreur personnalisés
     */
    public function messages(): array
    {
        return [
            'typelogement.in' => 'Le type de logement sélectionné n\'est pas compatible avec le type de propriété.',
            'typelogement.required' => 'Le type de logement est obligatoire.'
        ];
    }
}
