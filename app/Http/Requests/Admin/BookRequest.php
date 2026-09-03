<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
            'penulis' => ['nullable', 'string', 'max:255'],
            'penterjemah' => ['nullable', 'string', 'max:255'],
            'penerbit' => ['nullable', 'string', 'max:255'],
            'tahun' => ['nullable', 'integer', 'min:1900', 'max:'.(now()->year + 1)],
            'isbn' => ['nullable', 'string', 'max:20'],
            'sinopsis' => ['nullable', 'string'],
            'category_id' => ['required', 'exists:categories,id'],
            'cover' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:2048'],
            'cover_url' => ['nullable', 'url', 'max:255'],
            'remove_cover' => ['boolean'],
            'images' => ['nullable', 'array', 'max:5'],
            'images.*' => ['image', 'mimes:jpg,jpeg,png,webp,gif', 'max:2048'],
            'removed_images' => ['nullable', 'array'],
            'removed_images.*' => ['uuid'],
            'aktif' => ['boolean'],
            'is_preorder' => ['boolean'],
            'preorder_eta' => ['nullable', 'date'],
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
            'editions.*.harga_guru_type' => ['nullable', 'string', Rule::in(['percent', 'fixed'])],
            'editions.*.harga_guru_value' => ['nullable', 'integer', 'min:1', 'max:1000000000'],
        ];
    }

    /**
     * Normalisasi harga guru: jika type/value tidak lengkap, kosongkan keduanya.
     */
    protected function prepareForValidation(): void
    {
        if (! $this->has('editions') || ! is_array($this->input('editions'))) {
            return;
        }

        $editions = $this->input('editions');

        foreach ($editions as $index => $edition) {
            $type = $edition['harga_guru_type'] ?? null;
            $value = $edition['harga_guru_value'] ?? null;

            // Kosongkan jika salah satu tidak diisi
            if (empty($type) || empty($value)) {
                $editions[$index]['harga_guru_type'] = null;
                $editions[$index]['harga_guru_value'] = null;
            }
        }

        $this->merge(['editions' => $editions]);
    }

    /**
     * Validasi silang harga guru (percent 1-100, fixed >=1).
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $editions = $this->input('editions', []);

                foreach ($editions as $index => $edition) {
                    $type = $edition['harga_guru_type'] ?? null;
                    $value = $edition['harga_guru_value'] ?? null;

                    if ($type === null && $value === null) {
                        continue;
                    }

                    if ($type === 'percent' && $value !== null && ((int) $value < 1 || (int) $value > 100)) {
                        $validator->errors()->add("editions.{$index}.harga_guru_value", 'Diskon guru persen harus 1–100.');
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
            'judul.required' => 'Judul buku wajib diisi.',
            'editions.required' => 'Minimal satu cetakan wajib diisi.',
            'editions.min' => 'Minimal satu cetakan wajib diisi.',
            'editions.*.cetakan_ke.required' => 'Nomor cetakan wajib diisi.',
            'editions.*.nama.max' => 'Nama cetakan maksimal 100 karakter.',
            'editions.*.harga_beli.required' => 'Harga beli cetakan wajib diisi.',
            'editions.*.harga_jual.required' => 'Harga jual cetakan wajib diisi.',
            'editions.*.harga_beli.min' => 'Harga beli minimal 1.',
            'editions.*.harga_jual.min' => 'Harga jual minimal 1.',
            'editions.*.harga_guru_type.in' => 'Tipe harga guru harus percent atau fixed.',
            'editions.*.harga_guru_value.min' => 'Nilai harga guru minimal 1.',
            'category_id.required' => 'Kategori wajib diisi.',
            'images.max' => 'Maksimal 5 gambar galeri.',
            'images.*.image' => 'File galeri harus berupa gambar.',
            'images.*.max' => 'Ukuran gambar galeri maksimal 2 MB.',
            'removed_images.*.uuid' => 'Gambar galeri yang dihapus tidak valid.',
        ];
    }
}
