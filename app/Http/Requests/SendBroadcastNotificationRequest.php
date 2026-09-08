<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SendBroadcastNotificationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Only admins can send broadcast notifications
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
            'cible'   => 'required|string|in:proprietaires,locataires,tous',
            'titre'   => 'required|string|max:255',
            'message' => 'required|string',
        ];
    }
}