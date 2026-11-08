<?php

namespace App\Http\Requests;

use App\Models\MoneyEntry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMoneyEntryRequest extends FormRequest
{
    /** 10 million dh: a typo guard, not a limit anyone should meet. */
    public const MAX_AMOUNT = 10_000_000;

    /**
     * Single-user app: access is handled by the password gate, not here.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * "11,50" (a French keyboard) reads as 11.50; spaces in "1 500" are dropped;
     * an empty category means none.
     */
    protected function prepareForValidation(): void
    {
        $amount = $this->input('amount');
        if (is_string($amount)) {
            $this->merge(['amount' => str_replace([' ', "\u{00A0}", ','], ['', '', '.'], trim($amount))]);
        }

        foreach (['title', 'category'] as $field) {
            if (is_string($this->input($field))) {
                $this->merge([$field => trim($this->input($field)) ?: null]);
            }
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(MoneyEntry::TYPES)],
            'amount' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:'.self::MAX_AMOUNT],
            'title' => ['required', 'string', 'max:255'],
            'category' => ['sometimes', 'nullable', 'string', 'max:60'],
            'occurred_on' => ['required', 'date_format:Y-m-d'],
        ];
    }

    /**
     * The validated fields as columns: the amount in centimes.
     *
     * @return array<string, mixed>
     */
    public function entryAttributes(): array
    {
        $attributes = $this->safe()->except('amount');

        if ($this->has('amount')) {
            $attributes['amount_cents'] = (int) round((float) $this->input('amount') * 100);
        }

        return $attributes;
    }
}
