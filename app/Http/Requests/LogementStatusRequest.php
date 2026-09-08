<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LogementStatusRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
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
            'statut_publication' => 'required|in:publie,brouillon',
        ];
    }
}