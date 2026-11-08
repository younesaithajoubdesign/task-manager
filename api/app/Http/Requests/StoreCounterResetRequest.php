<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

class StoreCounterResetRequest extends FormRequest
{
    /**
     * Single-user app: access is handled by the password gate, not here.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * `reset_at` is optional: now by default, or an earlier moment when it is
     * logged late (or when counting starts from a day in the past).
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'reset_at' => ['sometimes', 'nullable', 'date', 'before_or_equal:now'],
        ];
    }

    public function resetAt(): Carbon
    {
        return $this->filled('reset_at') ? Carbon::parse($this->input('reset_at')) : now();
    }
}
