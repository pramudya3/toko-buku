<?php

namespace App\Http\Requests\Admin;

use App\Models\Book;
use App\Support\StoreSettings;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OrderStoreRequest extends FormRequest
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
        $rules = [
            'user_id' => [
                'nullable',
                Rule::exists('users', 'id')->where('is_admin', 0),
            ],
            'nama_pembeli' => ['required', 'string', 'max:255'],
            'whatsapp_pembeli' => ['nullable', 'string', 'max:20', 'regex:/^(62|0|8)8\d{7,12}$/'],
            'alamat' => ['nullable', 'string'],
            'provinsi' => ['nullable', 'string', 'max:100'],
            'kabupaten_kota' => ['nullable', 'string', 'max:100'],
            'kecamatan' => ['nullable', 'string', 'max:100'],
            'kelurahan' => ['nullable', 'string', 'max:100'],
            'kode_pos' => ['nullable', 'string', 'max:10'],
            'metode_bayar' => ['required', Rule::in(StoreSettings::enabledPaymentMethodValues() ?: ['__tidak_ada__'])],
            'sumber_pembelian' => ['nullable', 'string', 'max:50', Rule::in(StoreSettings::enabledSalesChannelValues() ?: ['__tidak_ada__'])],
            // Ekspedisi wajib saat order dikirim; ambil sendiri tanpa ongkir.
            // Default pembuatan = ambil sendiri.
            'ekspedisi' => [
                'nullable',
                Rule::requiredIf(fn (): bool => $this->input('metode_pengambilan') === 'kirim'),
                'string',
                'max:50',
            ],
            'courier_service_code' => ['nullable', 'string', 'max:50'],
            'shipping_cost' => ['nullable', 'integer', 'min:0'],
            'ongkir_estimasi' => ['nullable', 'string', 'max:100'],
            'metode_pengambilan' => ['nullable', Rule::in(['kirim', 'ambil'])],
            'is_dropship' => ['boolean'],
            'end_customer_name' => ['required_if:is_dropship,true', 'string', 'max:255'],
            'end_customer_whatsapp' => ['nullable', 'string', 'max:20', 'regex:/^(62|0|8)8\d{7,12}$/'],
            'end_customer_address' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.book_id' => [
                'required',
                Rule::exists('books', 'id')->where('aktif', 1),
            ],
            'items.*.book_edition_id' => ['nullable', 'string', 'exists:book_editions,id'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
        ];

        // Pasangan (book_id, book_edition_id) tidak boleh duplikat —
        // satu buku boleh muncul 2x bila cetakannya berbeda. Qty tidak
        // boleh melebihi stok master buku (stok gudang dicek saat proses).
        $rules['items'] = [
            'required',
            'array',
            'min:1',
            function (string $attribute, mixed $value, $fail): void {
                $seen = [];
                $rows = is_array($value) ? $value : [];
                $bookIds = array_values(array_unique(array_filter(
                    array_map(
                        fn ($row): string => (string) ($row['book_id'] ?? ''),
                        $rows,
                    ),
                )));

                $stocks = Book::query()
                    ->whereIn('id', $bookIds)
                    ->get(['id', 'stok', 'is_preorder'])
                    ->keyBy('id');

                foreach ($value as $row) {
                    $key = ((string) ($row['book_id'] ?? '')).':'.((string) ($row['book_edition_id'] ?? ''));

                    if (isset($seen[$key])) {
                        $fail('Item buku & cetakan tidak boleh duplikat.');

                        return;
                    }

                    $seen[$key] = true;

                    $book = $stocks->get((string) ($row['book_id'] ?? ''));

                    // Buku pre-order menunggu stok — batas memakai config.
                    $cap = $book?->is_preorder
                        ? (int) config('preorder.max_qty', 99)
                        : (int) ($book?->stok ?? 0);

                    if ((int) ($row['qty'] ?? 0) > $cap) {
                        $fail("Stok buku tidak mencukupi — maks {$cap}.");

                        return;
                    }
                }
            },
        ];

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nama_pembeli.required' => 'Nama pembeli wajib diisi.',
            'items.required' => 'Order minimal berisi 1 item buku.',
            'items.*.qty.min' => 'Qty minimal 1.',
            'whatsapp_pembeli.regex' => 'Format nomor WhatsApp tidak valid (contoh: 081234567890).',
            'end_customer_whatsapp.regex' => 'Format nomor WhatsApp tidak valid (contoh: 081234567890).',
        ];
    }
}
