<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadBuktiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return ['bukti' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:2048']];
    }
}
