<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSubscriptionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Only a proprietario authenticated user can initiate a subscription
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
            'plan_id'   => 'required|exists:plans,id',
            'operateur' => 'required|in:' . implode(',', config('luwaas.payment_methods.mobile_money')),
            'telephone' => 'nullable|string|max:20',
        ];
    }
}