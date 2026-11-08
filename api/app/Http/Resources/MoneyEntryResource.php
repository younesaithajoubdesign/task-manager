<?php

namespace App\Http\Resources;

use App\Models\MoneyEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin MoneyEntry
 */
class MoneyEntryResource extends JsonResource
{
    /**
     * Amounts go out in dirhams (11.5), stored in centimes (1150).
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'amount' => $this->amount_cents / 100,
            'title' => $this->title,
            'category' => $this->category,
            'occurred_on' => $this->occurred_on->toDateString(),
        ];
    }
}
