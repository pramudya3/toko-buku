<?php

namespace App\Http\Requests\Admin;

use App\Enums\PromotionType;
use App\Models\Promotion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
     * Cek eksklusivitas promosi satuan: sebuah buku hanya boleh dipakai di SATU
     * promosi satuan (percentage/fixed) dengan periode yang tumpang tindih.
     * Buku yang sudah dipakai promosi satuan tetap boleh masuk ke promosi bundle.
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $promoType = $this->input('promo_type');
                $bookIds = (array) $this->input('book_ids', []);

                // Bundle & global bebas — cek hanya untuk promosi satuan.
                if ($promoType === PromotionType::Bundle->value || $bookIds === []) {
                    return;
                }

                $currentPromotion = $this->route('promotion');

                $conflicts = Promotion::query()
                    ->where('promo_type', '!=', PromotionType::Bundle->value)
                    ->when($currentPromotion, fn ($query) => $query->whereKeyNot($currentPromotion->getKey()))
                    // Periode tumpang tindih dengan promo baru.
                    ->whereDate('start_date', '<=', $this->input('end_date'))
                    ->whereDate('end_date', '>=', $this->input('start_date'))
                    ->whereHas('books', fn ($query) => $query->whereIn('books.id', $bookIds))
                    ->with('books:id,judul')
                    ->get();

                if ($conflicts->isEmpty()) {
                    return;
                }

                $conflictingTitles = $conflicts
                    ->flatMap(fn (Promotion $promotion) => $promotion->books->whereIn('id', $bookIds)->pluck('judul'))
                    ->unique()
                    ->values();

                $validator->errors()->add(
                    'book_ids',
                    'Buku "'.$conflictingTitles->implode('", "').'" sudah digunakan pada promosi satuan lain. Pilih buku lain atau gunakan promosi bundle.',
                );
            },
        ];
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
            'discount_percentage' => ['required_if:promo_type,percentage,bundle', 'nullable', 'integer', 'min:1', 'max:100'],
            'promo_value' => ['required_if:promo_type,fixed', 'nullable', 'integer', 'min:0'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'is_active' => ['boolean'],
            'is_global' => ['required', 'boolean'],
            'book_ids' => [
                'nullable',
                'array',
                Rule::when(
                    $this->input('promo_type') === 'bundle',
                    ['required', 'min:2'],
                ),
                Rule::when(
                    ! $this->boolean('is_global') && $this->input('promo_type') !== 'bundle',
                    ['required', 'min:1'],
                ),
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
        ];
    }
}
