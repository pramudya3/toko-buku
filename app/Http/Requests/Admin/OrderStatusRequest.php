<?php

namespace App\Http\Requests\Admin;

use App\Enums\OrderStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OrderStatusRequest extends FormRequest
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
        return [
            // Transisi ke diproses wajib melalui endpoint yang mengisi ongkir dan gudang.
            'status' => [
                'required',
                Rule::in([
                    OrderStatus::Dikirim->value,
                    OrderStatus::Selesai->value,
                    OrderStatus::Batal->value,
                ]),
            ],
            // Resi & link tracking — wajib saat menandai Dikirim untuk
            // pengiriman manual (mis. via website ekspedisi).
            'awb' => ['nullable', 'string', 'max:100'],
            'biteship_courier_link' => ['nullable', 'url', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status' => 'Status order tidak valid.',
        ];
    }
}
