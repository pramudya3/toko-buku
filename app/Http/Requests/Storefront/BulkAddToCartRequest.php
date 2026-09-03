<?php

namespace App\Http\Requests\Storefront;

use Illuminate\Foundation\Http\FormRequest;

class BulkAddToCartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'book_ids' => ['required', 'array', 'min:1'],
            'book_ids.*' => ['required', 'string', 'exists:books,id'],
        ];
    }
}
