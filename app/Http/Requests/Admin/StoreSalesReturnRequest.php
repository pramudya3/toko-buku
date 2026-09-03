<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreSalesReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_admin === true;
    }

    public function rules(): array
    {
        return [
            'order_id' => ['required', 'string', 'exists:orders,id'],
            'return_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.order_item_id' => ['required', 'string', 'distinct', 'exists:order_items,id'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
            'items.*.condition' => ['required', 'string', 'in:baik,rusak'],
            'items.*.reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
