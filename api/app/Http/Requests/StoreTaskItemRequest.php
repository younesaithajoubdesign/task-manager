<?php

namespace App\Http\Requests;

use App\Rules\LinkUrl;
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
     * a path pasted from Finder ("/Users/…/a b.pdf") becomes a file:/// link,
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
                    str_starts_with($link, '/') => $this->fileLink($link),
                    preg_match('#^file:///#i', $link) === 1 => $this->fileLink(substr($link, 7)),
                    ! preg_match('#^[a-z][a-z0-9+.-]*://#i', $link) => 'https://'.$link,
                    default => $link,
                },
            ]);
        }
    }

    /**
     * "/Users/me/a b.pdf" → "file:///Users/me/a%20b.pdf" (an already encoded path stays as it is).
     */
    private function fileLink(string $path): string
    {
        $segments = array_map(fn (string $segment) => rawurlencode(rawurldecode($segment)), explode('/', $path));

        return 'file://'.implode('/', $segments);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'link' => ['sometimes', 'nullable', 'string', 'max:2048', new LinkUrl],
        ];
    }
}
