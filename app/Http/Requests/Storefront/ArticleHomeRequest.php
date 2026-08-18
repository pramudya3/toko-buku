<?php

namespace App\Http\Requests\Storefront;

use Illuminate\Foundation\Http\FormRequest;

class ArticleHomeRequest extends FormRequest
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
            'search' => ['nullable', 'string', 'max:255'],
            // Filter penulis (nama persis, string bebas) untuk daftar /penulis → /artikel.
            'penulis' => ['nullable', 'string', 'max:255'],
            // Kategori tunggal (storefront utama) — dipertahankan untuk kompatibilitas.
            'category_id' => ['nullable', 'string', 'exists:article_categories,id'],
            // Kategori jamak (proto-d): OR — artikel cocok bila masuk salah satu.
            'categories' => ['nullable', 'array', 'max:20'],
            'categories.*' => ['string', 'exists:article_categories,id'],
            'month' => ['nullable', 'integer', 'between:1,12'],
            'year' => ['nullable', 'integer', 'between:2000,2100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'search.max' => 'Pencarian maksimal 255 karakter.',
            'category_id.exists' => 'Kategori artikel tidak valid.',
            'categories.*.exists' => 'Kategori artikel tidak valid.',
            'month.between' => 'Bulan tidak valid.',
            'year.between' => 'Tahun tidak valid.',
        ];
    }
}
