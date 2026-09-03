<?php

namespace App\Http\Requests\Admin;

use App\Models\KasSubCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class CashFlowUpdateRequest extends FormRequest
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
            'flow_type' => ['required', 'string', 'in:income,expense'],
            'entry_date' => ['required', 'date'],
            'amount' => ['required', 'integer', 'min:1'],
            'description' => ['required', 'string', 'max:500'],
            'kas_category_id' => ['nullable', 'required_if:flow_type,expense', 'uuid', 'exists:cash_flow_categories,id'],
            'kas_sub_category_id' => ['nullable', 'required_if:flow_type,expense', 'uuid', 'exists:cash_flow_sub_categories,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $cat = $this->input('kas_category_id');
            $sub = $this->input('kas_sub_category_id');

            if ($cat && $sub && ! KasSubCategory::where('id', $sub)->where('cash_flow_category_id', $cat)->exists()) {
                $v->errors()->add('kas_sub_category_id', 'Sub kategori tidak sesuai dengan kategori.');
            }
        });
    }
}
