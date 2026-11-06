<?php

namespace App\Http\Requests;

use App\Rules\LinkUrl;

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
            'link' => ['sometimes', 'nullable', 'string', 'max:2048', new LinkUrl],
            'done' => ['sometimes', 'boolean'],
        ];
    }
}
