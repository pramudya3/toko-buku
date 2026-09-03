<?php

namespace App\Http\Requests\Admin;

use App\Enums\CustomerTier;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Exists;

class StoreConsignmentSaleRequest extends FormRequest
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
            'customer_id' => ['required', 'string', $this->bazafCustomerRule()],
            'sale_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.book_id' => ['required', 'string', 'distinct', 'exists:books,id'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
            'items.*.price' => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * Mitra konsinyasi harus customer aktif dengan tier Bazaf.
     */
    protected function bazafCustomerRule(): Exists
    {
        return (new Exists('users', 'id'))->where(
            fn ($query) => $query
                ->where('is_admin', false)
                ->where('status_pelanggan', CustomerTier::Bazaf->value),
        );
    }
}
