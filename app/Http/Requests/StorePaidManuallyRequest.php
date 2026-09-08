<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePaidManuallyRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $paiementId = $this->route('id');
        $paiement = \App\Models\Paiement::find($paiementId);
        return $paiement && $paiement->bail?->proprietaire_id === $this->user()->proprietaire->id;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'id'   => 'required|exists:paiements,id',
            'mode' => 'required|in:especes,wave_direct,orange_direct,free_money,virement,cheque',
        ];
    }
}