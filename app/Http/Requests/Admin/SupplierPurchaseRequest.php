<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SupplierPurchaseRequest extends FormRequest
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
            'ref_code' => ['required', 'string', 'max:50', 'unique:supplier_purchases,ref_code'],
            'purchase_date' => ['required', 'date'],
            'warehouse_kode' => [
                'required',
                // Gudang tujuan barang masuk — defect tidak boleh jadi tujuan.
                Rule::exists('warehouses', 'kode')->where('is_defect', 0),
            ],
            'notes' => ['nullable', 'string'],
            'paid_amount' => ['nullable', 'integer', 'min:0'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.book_id' => ['required', 'string', 'exists:books,id', 'distinct'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
            'items.*.price' => ['required', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ref_code.required' => 'Kode referensi wajib diisi.',
            'ref_code.unique' => 'Kode referensi sudah dipakai pembelian lain.',
            'warehouse_kode.required' => 'Gudang tujuan wajib dipilih.',
            'items.required' => 'Minimal satu item barang.',
            'items.min' => 'Minimal satu item barang.',
            'items.*.qty.min' => 'Jumlah item minimal 1.',
            'items.*.book_id.distinct' => 'Ada item buku yang sama diulang.',
        ];
    }
}
