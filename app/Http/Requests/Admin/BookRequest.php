<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BookRequest extends FormRequest
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
            'judul' => ['required', 'string', 'max:255'],
            'kode_sku' => [
                'nullable',
                'string',
                'max:20',
                'regex:/^[A-Za-z0-9-]+$/',
                Rule::unique('books', 'kode_sku')
                    ->whereNull('deleted_at')
                    ->ignore($this->route('book')),
            ],
            'penulis' => ['required', 'string', 'max:255'],
            'penerbit' => ['nullable', 'string', 'max:255'],
            'tahun' => ['nullable', 'integer', 'min:1900', 'max:'.(now()->year + 1)],
            'isbn' => ['nullable', 'string', 'max:20'],
            'sinopsis' => ['nullable', 'string'],
            'harga' => ['required', 'integer', 'min:0'],
            'stok' => $this->isMethod('post')
                ? ['nullable', 'integer', 'min:0']
                : ['prohibited'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'cover' => ['nullable', 'image', 'max:2048'],
            'cover_url' => ['nullable', 'url', 'max:255'],
            'aktif' => ['boolean'],
            'is_preorder' => ['boolean'],
            'po_label' => ['nullable', 'string', 'max:100'],
            'rating_umur' => ['nullable', 'string', 'max:50'],
            'dimensi' => ['nullable', 'string', 'max:100'],
            'kemasan' => ['nullable', 'string', 'max:100'],
            'berat_gr' => ['nullable', 'integer', 'min:0'],
            'jumlah_halaman' => ['nullable', 'integer', 'min:0'],
            'jenis_kertas' => ['nullable', 'string', 'max:100'],
            'cetakan' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'judul.required' => 'Judul buku wajib diisi.',
            'harga.required' => 'Harga wajib diisi.',
            'harga.integer' => 'Harga harus bilangan bulat (rupiah).',
            'harga.min' => 'Harga tidak boleh negatif.',
        ];
    }
}
