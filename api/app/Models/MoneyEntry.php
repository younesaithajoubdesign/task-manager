<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One income or expense, in centimes, on the day it happened.
 *
 * @property Carbon $occurred_on
 */
class MoneyEntry extends Model
{
    public const TYPES = ['income', 'expense'];

    protected $fillable = ['type', 'amount_cents', 'title', 'category', 'occurred_on'];

    protected $casts = [
        'amount_cents' => 'integer',
        'occurred_on' => 'date',
    ];

    /**
     * Stored as a bare Y-m-d, so date ranges compare as plain strings
     * (the date cast alone would store "Y-m-d 00:00:00").
     */
    protected function occurredOn(): Attribute
    {
        return Attribute::set(fn (mixed $value) => Carbon::parse($value)->toDateString());
    }

    /** Newest day first; on one day, the latest typed first. */
    public function scopeLatestFirst(Builder $query): Builder
    {
        return $query->orderByDesc('occurred_on')->orderByDesc('id');
    }

    public function scopeBetween(Builder $query, Carbon $from, Carbon $to): Builder
    {
        return $query->whereBetween('occurred_on', [$from->toDateString(), $to->toDateString()]);
    }
}
