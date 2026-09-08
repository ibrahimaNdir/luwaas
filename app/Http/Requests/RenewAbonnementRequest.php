<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RenewAbonnementRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Only a proprietario with an active subscription can renew
        return $this->user()->proprietaire !== null && $this->user()->proprietaire->hasActiveSubscription();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'plan_id'   => 'required|exists:plans,id',
            'operateur' => 'required|in:' . implode(',', config('luwaas.payment_methods.mobile_money')),
            'telephone' => 'nullable|string|max:20',
        ];
    }
}