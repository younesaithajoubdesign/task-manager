<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Validator;

/**
 * ?from=&to= (Y-m-d, both optional) for the money list and statistics:
 * the last 30 days by default. No upper limit: "all time" is a valid window.
 */
class MoneyPeriodRequest extends FormRequest
{
    public const DEFAULT_DAYS = 30;

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
     * @return array<int, \Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isEmpty() && $this->from()->gt($this->to())) {
                    $validator->errors()->add('to', 'The end date must be on or after the start date.');
                }
            },
        ];
    }

    public function to(): Carbon
    {
        return $this->filled('to')
            ? Carbon::createFromFormat('Y-m-d', $this->input('to'))->startOfDay()
            : today();
    }

    public function from(): Carbon
    {
        return $this->filled('from')
            ? Carbon::createFromFormat('Y-m-d', $this->input('from'))->startOfDay()
            : $this->to()->subDays(self::DEFAULT_DAYS - 1);
    }
}
