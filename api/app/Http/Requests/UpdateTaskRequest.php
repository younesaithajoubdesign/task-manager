<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class UpdateTaskRequest extends FormRequest
{
    /**
     * Single-user app: access is handled by the password gate, not here.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Every field is optional on PATCH: only what is sent changes.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'sub_category_id' => ['sometimes', 'required', 'integer', Rule::exists('sub_categories', 'id')],
            'notes' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'link' => ['sometimes', 'nullable', 'url', 'max:2048'],
            'type' => ['sometimes', Rule::in(['timed', 'open'])],
            'planned_minutes' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:1440'],
            'importance' => ['sometimes', Rule::in(['low', 'medium', 'high'])],
            'status' => ['sometimes', Rule::in(['todo', 'doing', 'done'])],
            'due_at' => ['sometimes', 'nullable', 'date'],
            'repeat' => ['sometimes', 'nullable', Rule::in(['daily', 'weekly'])],
            'repeat_days' => ['nullable', 'array', 'required_if:repeat,weekly'],
            'repeat_days.*' => ['integer', 'between:1,7', 'distinct'],
            'position' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    /**
     * Validated input ready for the model.
     *
     * - A date-only `due_at` (YYYY-MM-DD, what a date picker sends) means
     *   "by the end of that day", stored as 23:59 like the seeded tasks.
     * - `repeat_days` is cleared whenever the task is not weekly.
     *
     * @return array<string, mixed>
     */
    public function taskAttributes(): array
    {
        $attributes = $this->validated();

        if (is_string($attributes['due_at'] ?? null) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $attributes['due_at'])) {
            $attributes['due_at'] = Carbon::createFromFormat('Y-m-d', $attributes['due_at'])->setTime(23, 59);
        }

        if (array_key_exists('repeat', $attributes) && $attributes['repeat'] !== 'weekly') {
            $attributes['repeat_days'] = null;
        }

        return $attributes;
    }
}
