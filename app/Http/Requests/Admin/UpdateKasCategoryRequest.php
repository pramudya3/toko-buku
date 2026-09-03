<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateKasCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_admin === true;
    }

    public function rules(): array
    {
        $id = $this->route('kasCategory')?->id ?? $this->route('kas_category')?->id;

        return [
            'nama' => ['required', 'string', 'max:255', Rule::unique('cash_flow_categories', 'nama')->ignore($id)],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
