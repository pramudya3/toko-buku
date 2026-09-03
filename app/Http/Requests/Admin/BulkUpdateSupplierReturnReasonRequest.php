<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class BulkUpdateSupplierReturnReasonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_admin === true;
    }

    public function rules(): array
    {
        if ($this->has('ids')) {
            return [
                'ids' => ['required', 'array'],
                'ids.*' => ['required', 'exists:reasons,id'],
                'is_active' => ['required', 'boolean'],
            ];
        }

        return [
            'reasons' => ['required', 'array'],
            'reasons.*.id' => ['required', 'exists:reasons,id'],
            'reasons.*.sort_order' => ['required', 'integer', 'min:0'],
        ];
    }
}
