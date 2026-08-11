<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class InventoryAdjustmentRequest extends FormRequest
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
            'book_id' => ['required', 'exists:books,id'],
            'book_edition_id' => ['nullable', 'string', 'exists:book_editions,id'],
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'qty' => ['required', 'integer', 'not_in:0'],
            'notes' => ['required', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'book_id.required' => 'Buku wajib dipilih.',
            'warehouse_id.required' => 'Gudang wajib diisi.',
            'qty.required' => 'Selisih stok wajib diisi.',
            'qty.not_in' => 'Selisih stok tidak boleh 0.',
            'notes.required' => 'Alasan penyesuaian wajib diisi.',
        ];
    }
}
