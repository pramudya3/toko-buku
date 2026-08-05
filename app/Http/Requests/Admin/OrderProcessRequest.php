<?php

namespace App\Http\Requests\Admin;

use App\Enums\Warehouse;
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
        return [
            'shipping_cost' => ['required', 'integer', 'min:0'],
            'ekspedisi' => ['required', Rule::in(array_keys(config('shipping.couriers', [])))],
            'warehouse_origin' => [
                'required',
                Rule::in([
                    Warehouse::Malang->value,
                    Warehouse::Sidoarjo->value,
                ]),
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
