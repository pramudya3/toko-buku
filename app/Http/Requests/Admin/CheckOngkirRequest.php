<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class CheckOngkirRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_admin === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'postal_code' => ['required', 'string', 'max:10'],
            'weight_kg' => ['required', 'numeric', 'min:0', 'max:200'],
        ];
    }
}
