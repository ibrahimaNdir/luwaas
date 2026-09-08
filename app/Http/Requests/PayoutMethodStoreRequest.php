<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PayoutMethodStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->proprietaire !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'payout_channel' => ['required', 'string', Rule::in(config('luwaas.payout_channels', []))],
            'payout_phone'   => ['required', 'string', 'regex:/^\+?[1-9]\d{1,14}$/'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'payout_channel.in' => 'Le canal de paiement sélectionné n\'est pas supporté.',
        ];
    }
}