<?php

use App\Models\MoneyEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Sunday 8 November 2026.
    $this->travelTo('2026-11-08 12:00:00');

    $add = fn (string $type, int $cents, string $on, ?string $category, string $title) => MoneyEntry::create([
        'type' => $type, 'amount_cents' => $cents, 'title' => $title, 'category' => $category, 'occurred_on' => $on,
    ]);

    $add('income', 150000, '2026-11-02', 'Work', 'Last week'); // Monday
    $add('expense', 1100, '2026-11-08', 'Food', 'Food container'); // Sunday
    $add('expense', 2500, '2026-11-08', 'Food', 'Triangle cheese 16 pcs');
    $add('expense', 4000, '2026-11-05', null, 'Haircut'); // Thursday
    $add('expense', 10000, '2026-10-20', 'Transport', 'Train'); // before this week
});

function weekStats(): array
{
    return test()->getJson(route('money.stats', ['from' => '2026-11-02', 'to' => '2026-11-08']))
        ->assertOk()
        ->json('data');
}

it('says what is left and how long it lasts at the pace of the last 30 days', function () {
    $stats = weekStats();

    expect($stats['balance'])->toEqual(1324) // 1500 - 11 - 25 - 40 - 100
        ->and($stats['pace_per_day'])->toEqual(5.87) // 176 dh over 30 days
        ->and($stats['runway_days'])->toBe(225);
});

it('totals today, this week, this month and this year', function () {
    expect(weekStats()['calendar'])->toEqual([
        'today' => ['income' => 0, 'expense' => 36],
        'week' => ['income' => 1500, 'expense' => 76],
        'month' => ['income' => 1500, 'expense' => 76],
        'year' => ['income' => 1500, 'expense' => 176],
    ]);
});

it('averages the spending of the period per day, week, month and year', function () {
    $window = weekStats()['window'];

    expect($window)->toMatchArray([
        'days' => 7,
        'income' => 1500.0,
        'expense' => 76.0,
        'net' => 1424.0,
        'savings_rate' => 0.949,
        'per_day' => 10.86,
        'per_week' => 76.0,
        'per_month' => 330.46,
        'per_year' => 3965.57,
        'spending_days' => 2,
        'no_spend_days' => 5,
        'previous_expense' => 0.0,
        'expense_change' => null,
    ]);
});

it('compares the spending with the period just before', function () {
    $window = $this->getJson(route('money.stats', ['from' => '2026-10-26', 'to' => '2026-11-08']))
        ->json('data.window');

    // 12–25 Oct: 100 dh; 26 Oct – 8 Nov: 76 dh.
    expect($window['previous_expense'])->toEqual(100)
        ->and($window['expense_change'])->toEqual(-0.24);
});

it('breaks the spending down by category and by weekday', function () {
    $stats = weekStats();

    expect($stats['categories'])->toEqual([
        ['category' => 'Other', 'amount' => 40, 'count' => 1, 'share' => 0.526],
        ['category' => 'Food', 'amount' => 36, 'count' => 2, 'share' => 0.474],
    ])
        ->and(collect($stats['weekdays'])->pluck('average', 'weekday')->all())
        ->toEqual([1 => 0, 2 => 0, 3 => 0, 4 => 40, 5 => 0, 6 => 0, 7 => 36]);
});

it('lists the biggest expenses of the period', function () {
    expect(collect(weekStats()['top_expenses'])->pluck('title')->all())
        ->toBe(['Haircut', 'Triangle cheese 16 pcs', 'Food container']);
});

it('charts a short period per day and a long one per month', function () {
    $series = weekStats()['series'];
    expect($series['unit'])->toBe('day')
        ->and($series['points'])->toHaveCount(7)
        ->and(collect($series['points'])->firstWhere('key', '2026-11-08'))->toEqual(['key' => '2026-11-08', 'income' => 0, 'expense' => 36]);

    $long = $this->getJson(route('money.stats', ['from' => '2026-01-01', 'to' => '2026-11-08']))->json('data.series');
    expect($long['unit'])->toBe('month')
        ->and(collect($long['points'])->pluck('key')->all())->toHaveCount(11)
        ->and(collect($long['points'])->firstWhere('key', '2026-10')['expense'])->toEqual(100);
});

it('gives the last 12 months, this one included', function () {
    $months = weekStats()['months'];

    expect($months)->toHaveCount(12)
        ->and($months[0]['month'])->toBe('2025-12')
        ->and($months[11])->toEqual(['month' => '2026-11', 'income' => 1500, 'expense' => 76])
        ->and($months[10])->toEqual(['month' => '2026-10', 'income' => 0, 'expense' => 100]);
});

it('counts a period running past today only up to today', function () {
    $window = $this->getJson(route('money.stats', ['from' => '2026-11-01', 'to' => '2026-11-30']))->json('data.window');

    expect($window['days'])->toBe(8)
        ->and($window['per_day'])->toEqual(9.5); // 76 dh over 1–8 Nov
});

it('refuses a period that ends before it starts', function () {
    $this->getJson(route('money.stats', ['from' => '2026-11-08', 'to' => '2026-11-01']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('to');
});
