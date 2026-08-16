<?php

namespace App\Http\Requests\Storefront;

use App\Support\StoreSettings;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CheckoutRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'nama_pembeli' => ['required', 'string', 'max:255'],
            'whatsapp_pembeli' => ['nullable', 'string', 'max:20', 'regex:/^(62|0|8)8\d{7,12}$/'],
            'email_pembeli' => ['nullable', 'email', 'max:255'],
            'alamat' => ['nullable', 'string'],
            'provinsi' => ['nullable', 'string', 'max:100'],
            'kabupaten_kota' => ['nullable', 'string', 'max:100'],
            'kecamatan' => ['nullable', 'string', 'max:100'],
            'kelurahan' => ['nullable', 'string', 'max:100'],
            'kode_pos' => ['nullable', 'string', 'max:10'],
            'metode_bayar' => ['required', Rule::in(StoreSettings::enabledPaymentMethodValues() ?: ['__tidak_ada__'])],
            // Ekspedisi wajib salah satu kurir aktif (Settings → Ekspedisi);
            // wajib saat dikirim — ambil sendiri tanpa ongkir.
            'ekspedisi' => [
                'nullable',
                'string',
                'max:50',
                Rule::requiredIf(($this->input('metode_pengambilan') ?? 'kirim') !== 'ambil'),
                Rule::in(StoreSettings::enabledCourierCodes() ?: ['__tidak_ada__']),
            ],
            // Metode pengambilan: kirim / ambil sendiri (default kirim).
            'metode_pengambilan' => [
                'nullable',
                Rule::in(['kirim', 'ambil']),
            ],
            'courier_service_code' => ['nullable', 'string', 'max:50'],
            'selected_groups' => ['nullable', 'array'],
            'selected_groups.*' => ['string', 'max:50'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nama_pembeli.required' => 'Nama lengkap wajib diisi.',
            'metode_bayar.required' => 'Pilih metode pembayaran.',
            'whatsapp_pembeli.regex' => 'Format nomor WhatsApp tidak valid (contoh: 081234567890).',
        ];
    }
}
