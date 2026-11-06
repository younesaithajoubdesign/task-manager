<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTaskItemRequest extends FormRequest
{
    /**
     * Single-user app: access is handled by the password gate, not here.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * A link typed without its scheme ("youtube.com/…") gets https://,
     * and an empty one means no link.
     */
    protected function prepareForValidation(): void
    {
        $link = $this->input('link');

        if (is_string($link)) {
            $link = trim($link);
            $this->merge([
                'link' => match (true) {
                    $link === '' => null,
                    ! preg_match('#^[a-z][a-z0-9+.-]*://#i', $link) => 'https://'.$link,
                    default => $link,
                },
            ]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'link' => ['sometimes', 'nullable', 'url:http,https', 'max:2048'],
        ];
    }
}
