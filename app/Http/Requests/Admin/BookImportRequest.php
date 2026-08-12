<?php

namespace App\Http\Requests\Admin;

use App\Support\CsvHeaderValidator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class BookImportRequest extends FormRequest
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
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.mimes' => 'File harus berformat CSV.',
            'file.max' => 'Ukuran file maksimal 2 MB.',
        ];
    }

    /**
     * Validasi header CSV: buku mendukung multi-layout (5-kolom, invoice, pricelist).
     * Tolak file yang tidak cocok dengan salah satu layout yang dikenal.
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $file = $this->file('file');

                if ($file === null) {
                    return;
                }

                $result = CsvHeaderValidator::validateAny($file->getRealPath(), [
                    'template 5-kolom' => ['judul', 'penulis', 'harga_jual'],
                    'invoice' => ['nama barang', 'hrg jual'],
                    'pricelist' => ['judul buku', 'harga normal'],
                ]);

                if (! $result['valid']) {
                    $validator->errors()->add('file', $result['error']);
                }
            },
        ];
    }
}
