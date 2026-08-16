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
        return [
            'judul' => ['required', 'string', 'max:255'],
            'article_category_id' => ['required', 'uuid', Rule::exists('article_categories', 'id')->whereNull('deleted_at')],
            'penulis' => ['nullable', 'string', 'max:100'],
            'ringkasan' => ['required', 'string', 'max:500'],
            'isi' => ['required', 'string', 'max:50000'],
            'motif' => ['nullable', Rule::in(array_keys(Article::motifOptions()))],
            'cover' => ['nullable', 'image', 'max:2048'],
            'remove_cover' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'published_at' => ['nullable', 'date'],
        ];
    }
}
