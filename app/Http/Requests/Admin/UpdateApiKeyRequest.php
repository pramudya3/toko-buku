<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateApiKeyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_admin === true;
    }

    public function rules(): array
    {
        return [
            'api_key' => ['nullable', 'string', 'min:10', 'max:500'],
            'clear_key' => ['nullable', 'boolean'],
        ];
    }
}
