<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Validator;

class StatsRequest extends FormRequest
{
    /** Default window: the last 30 days, today included. */
    public const DEFAULT_DAYS = 30;

    /** Longest window one request may cover, so a typo cannot ask for decades. */
    public const MAX_DAYS = 366;

    /**
     * Single-user app: access is handled by the password gate, not here.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'from' => ['sometimes', 'date_format:Y-m-d'],
            'to' => ['sometimes', 'date_format:Y-m-d'],
        ];
    }

    /**
     * Compare the resolved dates, defaults included, once the formats are valid.
     *
     * @return array<int, \Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                if ($this->from()->gt($this->to())) {
                    $validator->errors()->add('to', 'The end date must be on or after the start date.');
                } elseif ($this->from()->diffInDays($this->to()) + 1 > self::MAX_DAYS) {
                    $validator->errors()->add('from', 'Ask for '.self::MAX_DAYS.' days at most.');
                }
            },
        ];
    }

    /** Last day of the window: `to`, or today. */
    public function to(): Carbon
    {
        return $this->filled('to')
            ? Carbon::createFromFormat('Y-m-d', $this->input('to'))->startOfDay()
            : today();
    }

    /** First day of the window: `from`, or 30 days ending on `to`. */
    public function from(): Carbon
    {
        return $this->filled('from')
            ? Carbon::createFromFormat('Y-m-d', $this->input('from'))->startOfDay()
            : $this->to()->subDays(self::DEFAULT_DAYS - 1);
    }
}
