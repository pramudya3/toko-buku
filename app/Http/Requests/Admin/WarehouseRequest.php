<?php

namespace App\Http\Requests\Admin;

use App\Models\Warehouse;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WarehouseRequest extends FormRequest
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
        $warehouse = $this->route('warehouse');

        $rules = [
            'nama' => ['required', 'string', 'max:255'],
            'alamat' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ];

        if ($warehouse === null) {
            // Kode hanya diisi saat membuat; tidak bisa diubah setelahnya.
            $rules['kode'] = [
                'required',
                'string',
                'max:50',
                'regex:/^[a-z0-9_\-]+$/',
                Rule::unique('warehouses', 'kode'),
            ];
        }

        // Gudang defect hanya boleh satu — berlaku saat create maupun update.
        $rules['is_defect'] = ['boolean', function (string $attribute, $value, $fail) use ($warehouse): void {
            if (! (bool) $value) {
                return;
            }

            // Gudang yang sudah defect tidak perlu dicek ulang.
            if ($warehouse !== null && $warehouse->is_defect) {
                return;
            }

            $existing = Warehouse::query()->defect()
                ->when($warehouse !== null, fn ($query) => $query->where('id', '!=', $warehouse->id))
                ->exists();

            if ($existing) {
                $fail('Gudang defect sudah ada — hanya boleh satu gudang khusus defect.');
            }
        }];

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nama.required' => 'Nama gudang wajib diisi.',
            'kode.required' => 'Kode gudang wajib diisi.',
            'kode.regex' => 'Kode hanya boleh huruf kecil, angka, garis bawah, atau strip.',
            'kode.unique' => 'Kode gudang sudah dipakai.',
        ];
    }
}
