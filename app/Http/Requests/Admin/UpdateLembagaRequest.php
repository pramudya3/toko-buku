<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLembagaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_admin === true;
    }

    public function rules(): array
    {
        return [
            'nama_lembaga' => ['required', 'string', 'max:150'],
            'tagline' => ['nullable', 'string', 'max:255'],
            'telepon' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'],
            'jam_operasional' => ['nullable', 'string', 'max:100'],
            'hari_buka' => ['nullable', 'string', 'max:100'],
            'jam_buka' => ['nullable', 'string', 'max:10'],
            'jam_tutup' => ['nullable', 'string', 'max:10'],
            'deskripsi' => ['nullable', 'string', 'max:2000'],
            'visi' => ['nullable', 'string', 'max:2000'],
            'misi' => ['nullable', 'string', 'max:2000'],
            'keamanan' => ['nullable', 'string', 'max:2000'],
            'syarat' => ['nullable', 'string', 'max:5000'],
            'alamat_jalan' => ['nullable', 'string', 'max:255'],
            'origin_postal_code' => ['required', 'string', 'max:10'],
            'provinsi' => ['nullable', 'string', 'max:100'],
            'kabupaten_kota' => ['nullable', 'string', 'max:100'],
            'kecamatan' => ['nullable', 'string', 'max:100'],
            'kelurahan' => ['nullable', 'string', 'max:100'],
            'logo' => ['nullable', 'image', 'max:2048'],
            'hapus_logo' => ['nullable', 'boolean'],
        ];
    }
}
