<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AdminSubscriptionUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Only admins can update plans
        return $this->user()->user_type === 'admin';
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // Note: the rule uses the route parameter $id
        return [
            'slug'             => 'sometimes|required|string|unique:plans,slug,' . $this->route('id'),
            'name'             => 'sometimes|required|string',
            'tier'             => 'sometimes|required|string',
            'billing_cycle'    => 'sometimes|nullable|string|in:monthly,yearly',
            'price_base_xof'   => 'sometimes|required|numeric|min:0',
            'price_per_property_xof' => 'sometimes|required|numeric|min:0',
            'price_xof'        => 'sometimes|required|numeric|min:0',
            'publications_max' => 'sometimes|nullable|integer',
            'features'         => 'sometimes|array',
            'is_active'        => 'sometimes|boolean',
        ];
    }
}