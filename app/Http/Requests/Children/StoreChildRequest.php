<?php

namespace App\Http\Requests\Children;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreChildRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->hasPermissionTo('children.create');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'first_name'    => 'required',
            'last_name'     => 'required',
            'date_of_birth' => 'required',
            'country'       => 'required',
            'city'          => 'required',
            'description'   => 'required',
            'team_id'       => 'nullable',
        ];
    }
}
