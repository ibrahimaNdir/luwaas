<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InitiateLoyerRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $paiementId = $this->route('paiementId');
        $paiement = \App\Models\Paiement::find($paiementId);
        return $paiement && $paiement->locataire_id === $this->user()->locataire->id;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'operateur' => 'required|in:' . implode(',', config('luwaas.payment_methods.mobile_money')),
            'telephone' => 'nullable|string|max:20',
        ];
    }
}