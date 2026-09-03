<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreKasSubCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_admin === true;
    }

    public function rules(): array
    {
        return [
            'kas_category_id' => ['required', 'exists:cash_flow_categories,id'],
            'nama' => ['required', 'string', 'max:255'],
        ];
    }
}
