<?php

namespace App\Http\Requests\Admin;

use App\Models\Article;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ArticleRequest extends FormRequest
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
        // Saat menyimpan sebagai draft, hanya judul & kategori yang wajib;
        // ringkasan/isi boleh kosong dan diisi belakangan sebelum terbit.
        $isDraft = $this->boolean('save_as_draft');

        return [
            'judul' => ['required', 'string', 'max:255'],
            'article_category_id' => ['required', 'uuid', Rule::exists('article_categories', 'id')->whereNull('deleted_at')],
            'penulis' => ['nullable', 'string', 'max:100'],
            'ringkasan' => [$isDraft ? 'nullable' : 'required', 'string', 'max:500'],
            'isi' => [$isDraft ? 'nullable' : 'required', 'string', 'max:50000'],
            'motif' => ['nullable', Rule::in(array_keys(Article::motifOptions()))],
            'cover' => ['nullable', 'image', 'max:2048'],
            'remove_cover' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'is_featured' => ['nullable', 'boolean'],
            'published_at' => ['nullable', 'date'],
            'save_as_draft' => ['nullable', 'boolean'],
        ];
    }
}
