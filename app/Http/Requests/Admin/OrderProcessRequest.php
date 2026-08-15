<?php

namespace App\Http\Requests\Admin;

use App\Support\StoreSettings;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OrderProcessRequest extends FormRequest
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
        $order = $this->route('order');

        return [
            // Ongkir memakai nilai tersimpan dari order (diisi saat create/
            // checkout) — tidak ditanyakan lagi di langkah proses.
            'shipping_cost' => ['nullable', 'integer', 'min:0'],
            // Ekspedisi wajib hanya untuk channel utama mode kirim;
            // marketplace (pencatatan) & ambil sendiri opsional.
            'ekspedisi' => [
                'nullable',
                Rule::requiredIf(
                    $order?->isMainChannel() === true
                        && ($order?->metode_pengambilan ?? 'kirim') !== 'ambil',
                ),
                Rule::in(StoreSettings::enabledCourierCodes() ?: ['__tidak_ada__']),
            ],
            'courier_service_code' => ['nullable', 'string', 'max:50'],
            'warehouse_origin' => [
                'required',
                // Hindari bind `false` (di SQLite jadi '') — pakai 0.
                Rule::exists('warehouses', 'kode')->where('is_defect', 0),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'shipping_cost.required' => 'Ongkir final wajib diisi.',
            'ekspedisi.required' => 'Ekspedisi wajib diisi.',
            'warehouse_origin.required' => 'Gudang asal wajib dipilih.',
            'warehouse_origin' => 'Gudang asal tidak valid.',
        ];
    }
}
