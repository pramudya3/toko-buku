<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateReceivableRequest extends FormRequest
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
            'amount' => ['required', 'integer', 'min:1'],
            'paid_at' => ['required', 'date'],
            'metode' => ['required', 'string', 'in:transfer,cod,cash'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
