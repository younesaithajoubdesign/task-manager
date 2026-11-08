<?php

namespace App\Services;

use App\Models\CounterReset;
use Illuminate\Support\Carbon;

/**
 * The days-without counter as the app shows it: whole days since the latest
 * reset, the best run so far, and the history of resets.
 */
class CounterSummary
{
    /** How many past resets the history lists. */
    public const HISTORY_LIMIT = 100;

    /**
     * @return array{
     *     started_at: ?string,
     *     days: int,
     *     seconds: int,
     *     best_days: int,
     *     resets_count: int,
     *     resets: list<array{id: int, reset_at: string, run_days: ?int}>
     * }
     */
    public function build(): array
    {
        $resets = CounterReset::query()->orderBy('reset_at')->orderBy('id')->get();
        $current = $resets->last();
        $seconds = $current ? max(0, (int) $current->reset_at->diffInSeconds(now())) : 0;

        // Each reset ends the run that the previous one started.
        $runs = $resets->values()->map(fn (CounterReset $reset, int $i) => [
            'id' => $reset->id,
            'reset_at' => $reset->reset_at->toIso8601String(),
            'run_days' => $i === 0 ? null : $this->wholeDays($resets[$i - 1]->reset_at, $reset->reset_at),
        ]);

        return [
            'started_at' => $current?->reset_at->toIso8601String(),
            'days' => intdiv($seconds, 86400),
            'seconds' => $seconds,
            'best_days' => (int) max($runs->max('run_days') ?? 0, intdiv($seconds, 86400)),
            'resets_count' => max(0, $resets->count() - 1),
            'resets' => $runs->reverse()->take(self::HISTORY_LIMIT)->values()->all(),
        ];
    }

    private function wholeDays(Carbon $from, Carbon $to): int
    {
        return intdiv(max(0, (int) $from->diffInSeconds($to)), 86400);
    }
}
