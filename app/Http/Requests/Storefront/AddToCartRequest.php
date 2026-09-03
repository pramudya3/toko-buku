<?php

namespace App\Http\Requests\Storefront;

use Illuminate\Foundation\Http\FormRequest;

class AddToCartRequest extends FormRequest
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
            'book_id' => ['required', 'exists:books,id'],
            'book_edition_id' => ['nullable', 'string', 'exists:book_editions,id'],
            'qty' => ['required', 'integer', 'min:1'],
        ];
    }
}
