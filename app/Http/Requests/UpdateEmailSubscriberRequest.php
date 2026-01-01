<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateEmailSubscriberRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $emailRule = app()->environment('production') ? 'email:rfc,dns' : 'email:rfc';

        return [
            'email' => ['sometimes', 'string', 'max:255', $emailRule],
            'first_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'last_name'  => ['sometimes', 'nullable', 'string', 'max:255'],

            'unsubscribed_at' => ['sometimes', 'nullable', 'date'],
        ];
    }
}
