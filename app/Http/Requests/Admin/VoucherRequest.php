<?php

namespace App\Http\Requests\Admin;

use App\Enums\VoucherScope;
use App\Enums\VoucherType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class VoucherRequest extends FormRequest
{
    /**
     * Kode dinormalisasi ke UPPERCASE sebelum validasi — aturan unique
     * membandingkan nilai yang sama seperti saat disimpan.
     */
    protected function prepareForValidation(): void
    {
        if (filled($this->input('kode'))) {
            $this->merge(['kode' => Str::upper($this->input('kode'))]);
        }
    }

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
            'nama' => ['required', 'string', 'max:255'],
            'kode' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('vouchers', 'kode')->ignore($this->route('voucher')),
            ],
            'voucher_type' => ['required', Rule::enum(VoucherType::class)],
            'discount_scope' => ['required', Rule::enum(VoucherScope::class)],
            'discount_percentage' => ['required_if:voucher_type,percentage', 'nullable', 'integer', 'min:1', 'max:100'],
            'discount_value' => ['required_if:voucher_type,fixed', 'nullable', 'integer', 'min:1'],
            'min_order_amount' => ['nullable', 'integer', 'min:0'],
            'max_uses' => ['nullable', 'integer', 'min:1'],
            'max_uses_per_user' => ['nullable', 'integer', 'min:1'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'end_date.after_or_equal' => 'Tanggal akhir harus sama atau setelah tanggal mulai.',
            'discount_percentage.required_if' => 'Diskon persentase wajib diisi untuk tipe persentase.',
            'discount_value.required_if' => 'Nominal diskon wajib diisi untuk tipe nominal.',
            'kode.unique' => 'Kode voucher sudah dipakai voucher lain.',
        ];
    }
}
