<?php

namespace App\Http\Requests\Admin;

use App\Enums\PromotionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PromotionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->is_admin === true;
    }

    /**
     * Normalize the legacy empty-book-list representation of global promos.
     */
    protected function prepareForValidation(): void
    {
        if (! $this->has('is_global')) {
            $this->merge([
                'is_global' => empty($this->input('book_ids', [])),
            ]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'promo_name' => ['required', 'string', 'max:255'],
            'promo_type' => ['required', Rule::enum(PromotionType::class)],
            'discount_percentage' => ['required_if:promo_type,percentage', 'nullable', 'integer', 'min:1', 'max:100'],
            'promo_value' => ['required_if:promo_type,fixed', 'nullable', 'integer', 'min:0'],
            'bundle_qty' => ['required_if:promo_type,bundle', 'nullable', 'integer', 'min:2'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'is_active' => ['boolean'],
            'is_global' => ['required', 'boolean'],
            'book_ids' => [
                'nullable',
                'array',
                Rule::when(! $this->boolean('is_global'), ['required', 'min:1']),
            ],
            'book_ids.*' => ['exists:books,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'end_date.after_or_equal' => 'Tanggal akhir harus sama atau setelah tanggal mulai (PROM-04).',
            'discount_percentage.required_if' => 'Diskon persentase wajib diisi untuk tipe persentase.',
            'promo_value.required_if' => 'Harga tetap wajib diisi untuk tipe fixed.',
            'bundle_qty.required_if' => 'Jumlah bundle wajib diisi untuk tipe bundle.',
        ];
    }
}
