<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AdminSubscriptionStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Only admins can create plans
        return $this->user()->user_type === 'admin';
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'slug'             => 'required|string|unique:plans,slug',
            'name'             => 'required|string',
            'tier'             => 'required|string',
            'billing_cycle'    => 'nullable|string|in:monthly,yearly',
            'price_base_xof'   => 'required|numeric|min:0',
            'price_per_property_xof' => 'required|numeric|min:0',
            'price_xof'        => 'required|numeric|min:0',
            'publications_max' => 'nullable|integer',
            'features'         => 'sometimes|array',
            'is_active'        => 'sometimes|boolean',
        ];
    }
}