<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class UpdateSubCategoryRequest extends StoreSubCategoryRequest
{
    /**
     * Every field is optional on PATCH; moving to another category is allowed.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'category_id' => ['sometimes', 'integer', Rule::exists('categories', 'id')],
            'title' => ['sometimes', 'required', 'string', 'max:60'],
            'color' => ['sometimes', 'regex:/^#[0-9a-f]{6}$/'],
            'position' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
