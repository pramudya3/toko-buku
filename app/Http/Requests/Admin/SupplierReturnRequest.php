<?php

namespace App\Http\Requests\Admin;

use App\Models\BookEdition;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
            'reason' => ['nullable', 'string', 'max:255'],
            'source' => ['nullable', Rule::in(['defect', 'normal'])],
            'shipping_cost' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.book_id' => ['required', 'string', 'exists:books,id'],
            'items.*.book_edition_id' => ['nullable', 'string', 'exists:book_editions,id'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
            'items.*.price' => ['required', 'integer', 'min:0'],
            // reason & source per item tetap didukung untuk backward, tapi global lebih diutamakan
            'items.*.reason' => ['nullable', 'string', 'max:255'],
            'items.*.source' => ['nullable', Rule::in(['defect', 'normal'])],
            'items.*.allocations' => ['nullable', 'array', 'min:1'],
            'items.*.allocations.*.warehouse_kode' => ['required', 'string', Rule::exists('warehouses', 'kode')->where('is_defect', 0)],
            'items.*.allocations.*.qty' => ['required', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $items = $this->input('items', []);
                $seen = [];
                foreach ($items as $index => $item) {
                    $key = ($item['book_id'] ?? '').':'.($item['book_edition_id'] ?? 'null');
                    if (isset($seen[$key])) {
                        $validator->errors()->add("items.{$index}.book_id", 'Ada item buku yang sama diulang.');
                    }
                    $seen[$key] = true;

                    if (! empty($item['book_edition_id']) && ! empty($item['book_id'])) {
                        $exists = BookEdition::where('id', $item['book_edition_id'])->where('book_id', $item['book_id'])->exists();
                        if (! $exists) {
                            $validator->errors()->add("items.{$index}.book_edition_id", 'Cetakan tidak sesuai dengan buku.');
                        }
                    }
                }
                // Jika reason global tidak ada, pastikan per-item ada
                $globalReason = $this->input('reason');
                if (empty($globalReason)) {
                    foreach ($items as $index => $item) {
                        if (empty($item['reason'])) {
                            $validator->errors()->add("items.{$index}.reason", 'Alasan retur wajib diisi.');
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
            'items.required' => 'Minimal satu item barang.',
            'items.min' => 'Minimal satu item barang.',
            'items.*.qty.min' => 'Jumlah item minimal 1.',
            'items.*.reason.required' => 'Alasan retur wajib diisi.',
            'items.*.source.required' => 'Sumber stok wajib dipilih.',
            'items.*.book_id.distinct' => 'Ada item buku yang sama diulang.',
        ];
    }
}
