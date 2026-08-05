<?php

namespace App\Http\Requests\Admin;

use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OrderStoreRequest extends FormRequest
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
            'user_id' => [
                'nullable',
                Rule::exists('users', 'id')->where('is_admin', 0),
            ],
            'nama_pembeli' => ['required', 'string', 'max:255'],
            'whatsapp_pembeli' => ['nullable', 'string', 'max:20'],
            'alamat' => ['nullable', 'string'],
            'provinsi' => ['nullable', 'string', 'max:100'],
            'kabupaten_kota' => ['nullable', 'string', 'max:100'],
            'kecamatan' => ['nullable', 'string', 'max:100'],
            'kode_pos' => ['nullable', 'string', 'max:10'],
            'metode_bayar' => ['required', Rule::enum(PaymentMethod::class)],
            'ekspedisi' => ['nullable', 'string', 'max:50'],
            'ongkir_estimasi' => ['nullable', 'integer', 'min:0'],
            'is_dropship' => ['boolean'],
            'end_customer_name' => ['required_if:is_dropship,true', 'string', 'max:255'],
            'end_customer_whatsapp' => ['nullable', 'string', 'max:20'],
            'end_customer_address' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.book_id' => [
                'required',
                'distinct',
                Rule::exists('books', 'id')->where('aktif', 1),
            ],
            'items.*.qty' => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nama_pembeli.required' => 'Nama pembeli wajib diisi.',
            'items.required' => 'Order minimal berisi 1 item buku.',
            'items.*.qty.min' => 'Qty minimal 1.',
        ];
    }
}
