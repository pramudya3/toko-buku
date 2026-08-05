<?php

namespace App\Http\Requests\Admin;

use App\Enums\MovementType;
use App\Enums\Warehouse;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InventoryMovementRequest extends FormRequest
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
            'book_id' => ['required', 'exists:books,id'],
            'type' => ['required', Rule::enum(MovementType::class)],
            'qty' => ['required', 'integer', 'min:1'],
            'from_warehouse' => ['required_if:type,transfer', 'required_if:type,out', 'required_if:type,defect', 'nullable', Rule::enum(Warehouse::class)],
            'to_warehouse' => ['required_if:type,transfer', 'required_if:type,in', 'required_if:type,defect', 'nullable', Rule::enum(Warehouse::class)],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'qty.min' => 'Jumlah mutasi minimal 1.',
            'from_warehouse.required_if' => 'Gudang asal wajib diisi.',
            'to_warehouse.required_if' => 'Gudang tujuan wajib diisi.',
        ];
    }
}
