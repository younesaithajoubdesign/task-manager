<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSubCategoryRequest extends FormRequest
{
    /**
     * Single-user app: access is handled by the password gate, not here.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Colours are stored lower-case so "#EC4899" and "#ec4899" are one colour.
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('color'))) {
            $this->merge(['color' => strtolower($this->input('color'))]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')],
            'title' => ['required', 'string', 'max:60'],
            'color' => ['sometimes', 'regex:/^#[0-9a-f]{6}$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'color.regex' => 'The colour must be a hex value like #3b82f6.',
        ];
    }
}
