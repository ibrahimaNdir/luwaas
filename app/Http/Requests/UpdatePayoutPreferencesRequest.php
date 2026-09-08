<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePayoutPreferencesRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $payoutMethodId = $this->input('payout_method_id');
        $proprietaireId = $this->user()->proprietaire->id ?? null;
        if (!$proprietaireId) {
            return false;
        }
        // Ensure the payout method exists and belongs to this proprietor
        return \App\Models\PayoutMethod::where('id', $payoutMethodId)
            ->where('proprietaire_id', $proprietaireId)
            ->exists();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'payout_method_id' => 'required|exists:payout_methods,id',
        ];
    }
}