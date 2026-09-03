<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSupplierReturnReasonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_admin === true;
    }

    public function rules(): array
    {
        $id = $this->route('alasanRetur')?->id ?? $this->route('supplierReturnReason')?->id;

        return [
            'code' => ['required', 'string', 'max:50', 'regex:/^[a-z0-9-]+$/', Rule::unique('reasons', 'code')->ignore($id)],
            'name' => ['required', 'string', 'max:150'],
            'category' => ['required', Rule::in(['umum', 'cacat', 'salah_kirim'])],
            'type' => ['nullable', Rule::in(['supplier', 'sales', 'adjustment'])],
            'is_active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
