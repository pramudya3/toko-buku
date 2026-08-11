<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_admin === true;
    }

    /**
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
            'penterjemah' => ['nullable', 'string', 'max:255'],
            'penerbit' => ['nullable', 'string', 'max:255'],
            'tahun' => ['nullable', 'integer', 'min:1900', 'max:'.(now()->year + 1)],
            'isbn' => ['nullable', 'string', 'max:20'],
            'sinopsis' => ['nullable', 'string'],
            'category_id' => ['required', 'exists:categories,id'],
            'cover' => ['nullable', 'image', 'max:2048'],
            'cover_url' => ['nullable', 'url', 'max:255'],
            'remove_cover' => ['boolean'],
            'images' => ['nullable', 'array', 'max:5'],
            'images.*' => ['image', 'max:2048'],
            'removed_images' => ['nullable', 'array'],
            'removed_images.*' => ['uuid'],
            'aktif' => ['boolean'],
            'rating_umur' => ['nullable', 'string', 'max:50'],
            'dimensi' => ['nullable', 'string', 'max:100'],
            'kemasan' => ['nullable', 'string', 'max:100'],
            'berat_gr' => ['nullable', 'integer', 'min:0'],
            'jumlah_halaman' => ['nullable', 'integer', 'min:0'],
            'jenis_kertas' => ['nullable', 'string', 'max:100'],
            'cetakan' => ['nullable', 'string', 'max:100'],
            'bahasa' => ['nullable', 'string', 'max:50'],
            'jenis_cover' => ['nullable', 'string', 'max:50'],

            // Editions — minimal 1 cetakan
            'editions' => ['required', 'array', 'min:1'],
            'editions.*.cetakan_ke' => ['required', 'integer', 'min:1'],
            'editions.*.nama' => ['nullable', 'string', 'max:100'],
            'editions.*.harga_beli' => ['required', 'integer', 'min:1'],
            'editions.*.harga_jual' => ['required', 'integer', 'min:1'],
            'editions.*.is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'judul.required' => 'Judul buku wajib diisi.',
            'editions.required' => 'Minimal satu cetakan wajib diisi.',
            'editions.min' => 'Minimal satu cetakan wajib diisi.',
            'editions.*.cetakan_ke.required' => 'Nomor cetakan wajib diisi.',
            'editions.*.nama.max' => 'Nama cetakan maksimal 100 karakter.',
            'editions.*.harga_beli.required' => 'Harga beli cetakan wajib diisi.',
            'editions.*.harga_jual.required' => 'Harga jual cetakan wajib diisi.',
            'editions.*.harga_beli.min' => 'Harga beli minimal 1.',
            'editions.*.harga_jual.min' => 'Harga jual minimal 1.',
            'category_id.required' => 'Kategori wajib diisi.',
            'images.max' => 'Maksimal 5 gambar galeri.',
            'images.*.image' => 'File galeri harus berupa gambar.',
            'images.*.max' => 'Ukuran gambar galeri maksimal 2 MB.',
            'removed_images.*.uuid' => 'Gambar galeri yang dihapus tidak valid.',
        ];
    }
}
