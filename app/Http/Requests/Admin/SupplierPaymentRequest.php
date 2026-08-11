<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SupplierPaymentRequest extends FormRequest
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
            'amount' => ['required', 'integer', 'min:1'],
            'payment_date' => ['required', 'date'],
            'purchase_id' => [
                'nullable',
                'string',
                // Faktur wajib milik supplier terpilih — cegah saldo antar-supplier tertukar.
                Rule::exists('supplier_purchases', 'id')->where('supplier_id', $this->input('supplier_id')),
            ],
            'notes' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'amount.required' => 'Jumlah bayar wajib diisi.',
            'amount.min' => 'Jumlah bayar minimal 1.',
        ];
    }
}
