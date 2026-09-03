<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreStockRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return ['whatsapp_number' => ['required', 'string', 'max:20', 'regex:/^(62|0|8)8\d{7,12}$/']];
    }
}
