<?php

namespace App\Http\Requests\Admin;

use App\Models\BookEdition;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
                'required_without:warehouse_kodes',
                'nullable',
                'string',
                Rule::exists('warehouses', 'kode')->where('is_defect', 0),
            ],
            'warehouse_kodes' => ['required_without:warehouse_kode', 'nullable', 'array', 'min:1'],
            'warehouse_kodes.*' => [
                'string',
                'distinct',
                Rule::exists('warehouses', 'kode')->where('is_defect', 0),
            ],
            'shipping_cost' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string'],
            'paid_amount' => ['nullable', 'integer', 'min:0'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.book_id' => ['required', 'string', 'exists:books,id', 'distinct'],
            'items.*.price' => ['required', 'integer', 'min:0'],
            'items.*.qty' => ['nullable', 'integer', 'min:1', 'required_without:items.*.allocations'],
            'items.*.allocations' => ['nullable', 'array', 'min:1', 'required_without:items.*.qty'],
            'items.*.allocations.*.warehouse_kode' => [
                'required',
                'string',
                Rule::exists('warehouses', 'kode')->where('is_defect', 0),
            ],
            'items.*.allocations.*.qty' => ['required', 'integer', 'min:0'],
            'items.*.book_edition_id' => ['nullable', 'string', 'exists:book_editions,id'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $warehouseKodes = $this->input('warehouse_kodes');
                if ($warehouseKodes === null && $this->filled('warehouse_kode')) {
                    $warehouseKodes = [$this->input('warehouse_kode')];
                }
                $warehouseKodes = is_array($warehouseKodes) ? $warehouseKodes : [];

                $items = $this->input('items', []);
                foreach ($items as $index => $item) {
                    if (isset($item['allocations']) && is_array($item['allocations'])) {
                        $total = 0;
                        foreach ($item['allocations'] as $alloc) {
                            $total += (int) ($alloc['qty'] ?? 0);
                            if (! in_array($alloc['warehouse_kode'] ?? '', $warehouseKodes, true)) {
                                $validator->errors()->add(
                                    "items.{$index}.allocations",
                                    'Gudang pada alokasi harus termasuk gudang tujuan yang dipilih.'
                                );
                            }
                        }
                        if ($total < 1) {
                            $validator->errors()->add(
                                "items.{$index}.allocations",
                                'Total qty untuk buku ini minimal 1.'
                            );
                        }
                    }
                    if (! empty($item['book_edition_id']) && ! empty($item['book_id'])) {
                        $exists = BookEdition::where('id', $item['book_edition_id'])
                            ->where('book_id', $item['book_id'])->exists();
                        if (! $exists) {
                            $validator->errors()->add(
                                "items.{$index}.book_edition_id",
                                'Cetakan tidak sesuai dengan buku.'
                            );
                        }
                    }
                }
            },
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
            'warehouse_kode.required_without' => 'Gudang tujuan wajib dipilih.',
            'warehouse_kodes.required_without' => 'Gudang tujuan wajib dipilih.',
            'warehouse_kodes.min' => 'Pilih minimal satu gudang tujuan.',
            'items.required' => 'Minimal satu item barang.',
            'items.min' => 'Minimal satu item barang.',
            'items.*.qty.min' => 'Jumlah item minimal 1.',
            'items.*.book_id.distinct' => 'Ada item buku yang sama diulang.',
            'items.*.allocations.required_without' => 'Alokasi qty wajib diisi.',
        ];
    }
}
