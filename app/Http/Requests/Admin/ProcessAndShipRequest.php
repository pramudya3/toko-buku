<?php

namespace App\Http\Requests\Admin;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Support\StoreSettings;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Alur gabungan "Proses & Kirim": konfirmasi lunas + proses + booking Biteship
 * (+ penjadwalan pickup). Field proses hanya wajib saat order masih
 * menunggu_konfirmasi; saat diproses (retry booking) bisa dikosongkan.
 */
class ProcessAndShipRequest extends FormRequest
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
        $needsProcess = $order instanceof Order
            && $order->status === OrderStatus::MenungguKonfirmasi;
        $isWebsite = $order instanceof Order && $order->sumber_pembelian === 'website';
        $isKirim = $order instanceof Order
            && ($order->metode_pengambilan ?? 'kirim') !== 'ambil';

        return [
            'konfirmasi_lunas' => ['nullable', 'boolean'],
            'shipping_cost' => [
                // Ambil sendiri tanpa ongkir.
                Rule::requiredIf($needsProcess && $isKirim),
                'integer', 'min:0',
            ],
            'ekspedisi' => [
                'nullable',
                Rule::requiredIf($needsProcess && $isWebsite),
                Rule::in(StoreSettings::enabledCourierCodes() ?: ['__tidak_ada__']),
            ],
            'courier_service_code' => ['nullable', 'string', 'max:50'],
            'warehouse_origin' => [
                Rule::requiredIf($needsProcess),
                // Hindari bind `false` (di SQLite jadi '') — pakai 0.
                Rule::exists('warehouses', 'kode')->where('is_defect', 0),
            ],
            'collection_method' => ['nullable', Rule::in(['pickup', 'drop_off'])],
            'pickup_date' => ['nullable', 'date'],
            'pickup_time' => ['nullable', 'date_format:H:i'],
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
            'collection_method' => 'Metode penyerahan tidak valid.',
        ];
    }
}
