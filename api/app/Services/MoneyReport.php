<?php

namespace App\Services;

use App\Models\MoneyEntry;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The numbers behind GET /api/money/stats: what is left, how fast it goes,
 * and where it goes. Amounts go out in dirhams (2 decimals), computed in
 * centimes. A window that runs past today is counted up to today.
 */
class MoneyReport
{
    /** Up to this many days the chart has a bar per day, beyond it one per month. */
    public const DAILY_SERIES_MAX_DAYS = 62;

    /** The pace used to say how long the balance lasts: the last 30 days. */
    public const PACE_DAYS = 30;

    public const TOP_EXPENSES = 5;

    private const DAYS_PER_MONTH = 30.4375;

    private const DAYS_PER_YEAR = 365.25;

    /**
     * @return array<string, mixed>
     */
    public function build(Carbon $from, Carbon $to): array
    {
        $today = today();
        $last = $to->copy()->min($today);
        $elapsedDays = $last->lt($from) ? 0 : (int) $from->diffInDays($last) + 1;

        $entries = MoneyEntry::query()->between($from, $to)->get();
        $expenses = $entries->where('type', 'expense');
        $income = (int) $entries->where('type', 'income')->sum('amount_cents');
        $spent = (int) $expenses->sum('amount_cents');
        $balance = $this->sum('income') - $this->sum('expense');

        $previousFrom = $from->copy()->subDays(max(1, (int) $from->diffInDays($to) + 1));
        $previousSpent = $this->sum('expense', $previousFrom, $from->copy()->subDay());

        $paceSpent = $this->sum('expense', $today->copy()->subDays(self::PACE_DAYS - 1), $today);
        $pacePerDay = $paceSpent / self::PACE_DAYS;

        $perDay = $elapsedDays > 0 ? $spent / $elapsedDays : 0;
        $spendingDays = $expenses->map(fn (MoneyEntry $entry) => $entry->occurred_on->toDateString())->unique()->count();

        return [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'balance' => $this->dh($balance),
            'runway_days' => $balance > 0 && $pacePerDay > 0 ? (int) floor($balance / $pacePerDay) : null,
            'pace_per_day' => $this->dh($pacePerDay),
            'calendar' => $this->calendar($today),
            'window' => [
                'days' => $elapsedDays,
                'income' => $this->dh($income),
                'expense' => $this->dh($spent),
                'net' => $this->dh($income - $spent),
                'savings_rate' => $income > 0 ? round(($income - $spent) / $income, 3) : null,
                'per_day' => $this->dh($perDay),
                'per_week' => $this->dh($perDay * 7),
                'per_month' => $this->dh($perDay * self::DAYS_PER_MONTH),
                'per_year' => $this->dh($perDay * self::DAYS_PER_YEAR),
                'spending_days' => $spendingDays,
                'no_spend_days' => max(0, $elapsedDays - $spendingDays),
                'previous_expense' => $this->dh($previousSpent),
                'expense_change' => $previousSpent > 0 ? round(($spent - $previousSpent) / $previousSpent, 3) : null,
            ],
            'categories' => $this->categories($expenses, $spent),
            'weekdays' => $this->weekdays($expenses, $from, $last),
            'series' => $this->series($entries, $from, $to),
            'top_expenses' => $expenses->sortByDesc('amount_cents')->take(self::TOP_EXPENSES)->values()
                ->map(fn (MoneyEntry $entry) => [
                    'id' => $entry->id,
                    'title' => $entry->title,
                    'category' => $entry->category,
                    'amount' => $this->dh($entry->amount_cents),
                    'occurred_on' => $entry->occurred_on->toDateString(),
                ])->all(),
            'months' => $this->months($today),
        ];
    }

    /** Total in centimes of one type, all time or between two days. */
    private function sum(string $type, ?Carbon $from = null, ?Carbon $to = null): int
    {
        return (int) MoneyEntry::query()
            ->where('type', $type)
            ->when($from && $to, fn ($query) => $query->between($from, $to))
            ->sum('amount_cents');
    }

    /**
     * Today, this week (from Monday), this month and this year.
     *
     * @return array<string, array{income: float, expense: float}>
     */
    private function calendar(Carbon $today): array
    {
        $starts = [
            'today' => $today->copy(),
            'week' => $today->copy()->startOfWeek(Carbon::MONDAY),
            'month' => $today->copy()->startOfMonth(),
            'year' => $today->copy()->startOfYear(),
        ];

        return collect($starts)->map(fn (Carbon $start) => [
            'income' => $this->dh($this->sum('income', $start, $today)),
            'expense' => $this->dh($this->sum('expense', $start, $today)),
        ])->all();
    }

    /**
     * Expenses by category, biggest first; no category reads "Other".
     *
     * @param  Collection<int, MoneyEntry>  $expenses
     * @return list<array{category: string, amount: float, count: int, share: float}>
     */
    private function categories(Collection $expenses, int $spent): array
    {
        return $expenses->groupBy(fn (MoneyEntry $entry) => $entry->category ?? 'Other')
            ->map(fn (Collection $group, string $category) => [
                'category' => $category,
                'cents' => (int) $group->sum('amount_cents'),
                'count' => $group->count(),
            ])
            ->sortByDesc('cents')
            ->values()
            ->map(fn (array $row) => [
                'category' => $row['category'],
                'amount' => $this->dh($row['cents']),
                'count' => $row['count'],
                'share' => $spent > 0 ? round($row['cents'] / $spent, 3) : 0.0,
            ])->all();
    }

    /**
     * Average spent on each weekday (1 = Monday … 7 = Sunday) of the window:
     * the weekday's total over how many of that weekday the window holds.
     *
     * @param  Collection<int, MoneyEntry>  $expenses
     * @return list<array{weekday: int, average: float}>
     */
    private function weekdays(Collection $expenses, Carbon $from, Carbon $last): array
    {
        $occurrences = array_fill(1, 7, 0);
        for ($day = $from->copy(); $day->lte($last); $day->addDay()) {
            $occurrences[$day->dayOfWeekIso]++;
        }

        $totals = $expenses->groupBy(fn (MoneyEntry $entry) => $entry->occurred_on->dayOfWeekIso)
            ->map(fn (Collection $group) => (int) $group->sum('amount_cents'));

        return collect(range(1, 7))->map(fn (int $weekday) => [
            'weekday' => $weekday,
            'average' => $occurrences[$weekday] > 0
                ? $this->dh($totals->get($weekday, 0) / $occurrences[$weekday])
                : 0.0,
        ])->all();
    }

    /**
     * Income and expense per day for a short window, per month for a long one.
     *
     * @param  Collection<int, MoneyEntry>  $entries
     * @return array{unit: string, points: list<array{key: string, income: float, expense: float}>}
     */
    private function series(Collection $entries, Carbon $from, Carbon $to): array
    {
        $daily = $from->diffInDays($to) + 1 <= self::DAILY_SERIES_MAX_DAYS;
        $keyOf = fn (Carbon $date) => $daily ? $date->toDateString() : $date->format('Y-m');

        $keys = [];
        for ($cursor = $from->copy(); $cursor->lte($to); $daily ? $cursor->addDay() : $cursor->addMonthNoOverflow()->startOfMonth()) {
            $keys[] = $keyOf($cursor);
        }

        $grouped = $entries->groupBy(fn (MoneyEntry $entry) => $keyOf($entry->occurred_on));

        return [
            'unit' => $daily ? 'day' : 'month',
            'points' => collect(array_unique($keys))->values()->map(fn (string $key) => [
                'key' => $key,
                'income' => $this->dh((int) $grouped->get($key, collect())->where('type', 'income')->sum('amount_cents')),
                'expense' => $this->dh((int) $grouped->get($key, collect())->where('type', 'expense')->sum('amount_cents')),
            ])->all(),
        ];
    }

    /**
     * Income and expense for each of the last 12 months, this one included.
     *
     * @return list<array{month: string, income: float, expense: float}>
     */
    private function months(Carbon $today): array
    {
        $first = $today->copy()->startOfMonth()->subMonths(11);

        $totals = MoneyEntry::query()
            ->whereDate('occurred_on', '>=', $first->toDateString())
            ->get()
            ->groupBy(fn (MoneyEntry $entry) => $entry->occurred_on->format('Y-m'));

        return collect(range(0, 11))->map(function (int $i) use ($first, $totals) {
            $month = $first->copy()->addMonths($i)->format('Y-m');
            $group = $totals->get($month, collect());

            return [
                'month' => $month,
                'income' => $this->dh((int) $group->where('type', 'income')->sum('amount_cents')),
                'expense' => $this->dh((int) $group->where('type', 'expense')->sum('amount_cents')),
            ];
        })->all();
    }

    /** Centimes to dirhams, 2 decimals. */
    private function dh(int|float $cents): float
    {
        return round($cents / 100, 2);
    }
}
