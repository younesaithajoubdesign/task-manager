<?php

namespace App\Http\Requests;

class UpdateTaskItemRequest extends StoreTaskItemRequest
{
    /**
     * Every field optional; `done` ticks or unticks the item.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'link' => ['sometimes', 'nullable', 'url:http,https', 'max:2048'],
            'done' => ['sometimes', 'boolean'],
        ];
    }
}
