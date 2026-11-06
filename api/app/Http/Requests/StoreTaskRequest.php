<?php

namespace App\Http\Requests;

use App\Models\SubCategory;

/**
 * Quick capture (SPEC §5.5): a title is the only required input.
 * Everything else uses the column defaults and is edited afterwards.
 */
class StoreTaskRequest extends UpdateTaskRequest
{
    /**
     * Without a sub-category, fall back to the quick-capture default so
     * "type a title, press Enter" never asks a further question.
     */
    protected function prepareForValidation(): void
    {
        if ($this->input('sub_category_id') === null) {
            $this->merge(['sub_category_id' => SubCategory::forQuickCapture()?->id]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'title' => ['required', 'string', 'max:255'],
            'sub_category_id' => ['required', 'integer', 'exists:sub_categories,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'sub_category_id.required' => 'Create a category and a sub-category first.',
        ];
    }
}
