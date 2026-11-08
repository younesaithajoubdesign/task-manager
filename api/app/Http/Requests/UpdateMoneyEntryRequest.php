<?php

namespace App\Http\Requests;

use App\Models\MoneyEntry;
use Illuminate\Validation\Rule;

class UpdateMoneyEntryRequest extends StoreMoneyEntryRequest
{
    /**
     * Every field optional: only what is sent changes.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'type' => ['sometimes', 'required', Rule::in(MoneyEntry::TYPES)],
            'amount' => ['sometimes', 'required', 'numeric', 'decimal:0,2', 'gt:0', 'max:'.self::MAX_AMOUNT],
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'category' => ['sometimes', 'nullable', 'string', 'max:60'],
            'occurred_on' => ['sometimes', 'required', 'date_format:Y-m-d'],
        ];
    }
}
