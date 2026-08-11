<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SupplierReturnRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->is_admin === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'supplier_id' => ['required', 'string', 'exists:suppliers,id'],
            'return_date' => ['required', 'date'],
            'purchase_id' => [
                'nullable',
                'string',
                // Faktur wajib milik supplier terpilih — cegah saldo antar-supplier tertukar.
                Rule::exists('supplier_purchases', 'id')->where('supplier_id', $this->input('supplier_id')),
            ],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.book_id' => ['required', 'string', 'exists:books,id', 'distinct'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
            'items.*.price' => ['required', 'integer', 'min:0'],
            'items.*.reason' => ['required', 'string', 'max:255'],
            'items.*.source' => ['required', Rule::in(['defect', 'normal'])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'items.required' => 'Minimal satu item barang.',
            'items.min' => 'Minimal satu item barang.',
            'items.*.qty.min' => 'Jumlah item minimal 1.',
            'items.*.reason.required' => 'Alasan retur wajib diisi.',
            'items.*.source.required' => 'Sumber stok wajib dipilih.',
            'items.*.book_id.distinct' => 'Ada item buku yang sama diulang.',
        ];
    }
}
