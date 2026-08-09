<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\Propriete;

class LogementRequest extends FormRequest
{
    private ?Propriete $proprieteCache = null;

    public function authorize(): bool
    {
        return auth()->check();
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'typelogement' => strtolower($this->typelogement ?? ''),
            'etat'         => strtolower($this->etat ?? ''),
        ]);
    }

    public function rules(): array
    {
        $propriete   = $this->getPropriete();
        $proprieteId = $this->route('proprieteId');
        $logementId  = $this->route('logement')?->id;

        $numeroRule = ($propriete && $propriete->type === 'immeuble')
            ? [
                'required',
                'string',
                'max:50',
                Rule::unique('logements', 'numero')
                    ->where('propriete_id', $proprieteId)
                    ->ignore($logementId)
            ]
            : ['nullable', 'string', 'max:50'];

        return [
            'numero'                => $numeroRule,
            'superficie'            => 'nullable|numeric|min:0',
            'meuble'                => 'required|boolean',
            'etat'                  => ['required', 'string', Rule::in(['neuf', 'bon', 'moyen'])],
            'typelogement'          => ['required', 'string', Rule::in($this->getTypesLogementAutorises($propriete))],
            'description'           => 'nullable|string',
            'prix_loyer'            => 'required|numeric|min:0',
            'nombre_chambres'       => 'required|integer|min:0',
            'nombre_salles_de_bain' => 'required|integer|min:0',
            'statut_publication'    => 'sometimes|string|in:publie,brouillon',
            'statut_occupe'         => 'sometimes|string|in:disponible,occupe',
        ];
    }

    private function getPropriete(): ?Propriete
    {
        if (!$this->proprieteCache) {
            $this->proprieteCache = Propriete::find($this->route('proprieteId'));
        }
        return $this->proprieteCache;
    }

    private function getTypesLogementAutorises(?Propriete $propriete): array
    {
        if (!$propriete) return [];

        return match ($propriete->type) {
            'maison'   => ['maison', 'chambre', 'studio', 'appartement'],
            'villa'    => ['villa'],
            'immeuble' => ['appartement', 'studio'],
            default    => []
        };
    }

    public function messages(): array
    {
        return [
            'typelogement.in'       => 'Le type de logement sélectionné n\'est pas compatible avec le type de propriété.',
            'typelogement.required' => 'Le type de logement est obligatoire.',
            'etat.in'               => 'L\'état doit être : neuf, bon, moyen ou en_travaux.',
            'etat.required'         => 'L\'état du logement est obligatoire.',
        ];
    }
}
