<?php

namespace App\Http\Requests\Admin;

use App\Enums\CustomerTier;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CustomerStoreRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', Rule::unique('users', 'email')->whereNull('deleted_at')],
            'password' => ['required', 'string', 'min:8'],
            'whatsapp_number' => ['nullable', 'string', 'max:20', 'regex:/^(62|0|8)8\d{7,12}$/'],
            'status_pelanggan' => ['required', Rule::enum(CustomerTier::class)],
            'is_active' => ['boolean'],
            'alamat' => ['nullable', 'string'],
            'provinsi' => ['nullable', 'string', 'max:100'],
            'kabupaten_kota' => ['nullable', 'string', 'max:100'],
            'kecamatan' => ['nullable', 'string', 'max:100'],
            'kelurahan' => ['nullable', 'string', 'max:100'],
            'village_code' => ['nullable', 'string', 'max:20'],
            'kode_pos' => ['nullable', 'string', 'max:10'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status_pelanggan' => 'Status pelanggan tidak valid.',
            'whatsapp_number.regex' => 'Format nomor WhatsApp tidak valid (contoh: 081234567890).',
        ];
    }
}
