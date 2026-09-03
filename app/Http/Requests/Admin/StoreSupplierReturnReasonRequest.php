<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSupplierReturnReasonRequest extends FormRequest
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
            'code' => ['required', 'string', 'max:50', 'regex:/^[a-z0-9-]+$/', 'unique:reasons,code'],
            'name' => ['required', 'string', 'max:150'],
            'category' => ['required', Rule::in(['umum', 'cacat', 'salah_kirim'])],
            'type' => ['nullable', Rule::in(['supplier', 'sales', 'adjustment'])],
            'is_active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
