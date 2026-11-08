<?php

use App\Models\MoneyEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo('2026-11-08 12:00:00');
});

function moneyEntry(string $type, int $cents, string $on, ?string $category = null, string $title = 'Something'): MoneyEntry
{
    return MoneyEntry::create([
        'type' => $type,
        'amount_cents' => $cents,
        'title' => $title,
        'category' => $category,
        'occurred_on' => $on,
    ]);
}

it('adds an expense and an income, amounts kept to the centime', function () {
    $this->postJson(route('money.store'), [
        'type' => 'expense', 'amount' => '11,50', 'title' => ' Food container ', 'category' => 'Food', 'occurred_on' => '2026-11-08',
    ])->assertCreated()->assertJson(['data' => [
        'type' => 'expense', 'amount' => 11.5, 'title' => 'Food container', 'category' => 'Food', 'occurred_on' => '2026-11-08',
    ]]);

    $this->postJson(route('money.store'), [
        'type' => 'income', 'amount' => '1 500', 'title' => 'Salary', 'category' => '', 'occurred_on' => '2026-11-02',
    ])->assertCreated()->assertJsonPath('data.amount', 1500)->assertJsonPath('data.category', null);

    expect(MoneyEntry::orderBy('id')->pluck('amount_cents')->all())->toBe([1150, 150000]);
});

it('refuses an entry with a wrong amount, type, title or date', function (array $override, string $field) {
    $valid = ['type' => 'expense', 'amount' => 25, 'title' => 'Cheese', 'occurred_on' => '2026-11-08'];

    $this->postJson(route('money.store'), [...$valid, ...$override])
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);

    expect(MoneyEntry::count())->toBe(0);
})->with([
    'zero' => [['amount' => 0], 'amount'],
    'negative' => [['amount' => -5], 'amount'],
    'three decimals' => [['amount' => '1.005'], 'amount'],
    'not a number' => [['amount' => 'abc'], 'amount'],
    'too big' => [['amount' => 10_000_001], 'amount'],
    'unknown type' => [['type' => 'loan'], 'type'],
    'no title' => [['title' => '  '], 'title'],
    'bad date' => [['occurred_on' => '08/11/2026'], 'occurred_on'],
]);

it('lists the entries of a period, newest first, with the categories used most', function () {
    moneyEntry('expense', 1100, '2026-11-08', 'Food', 'Container');
    $latest = moneyEntry('expense', 2500, '2026-11-08', 'Food', 'Cheese');
    moneyEntry('income', 150000, '2026-11-02', null, 'Salary');
    moneyEntry('expense', 10000, '2026-10-01', 'Transport', 'Train'); // outside the period

    $this->getJson(route('money.index', ['from' => '2026-11-01', 'to' => '2026-11-08']))
        ->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('data.0.id', $latest->id)
        ->assertJsonPath('data.2.title', 'Salary')
        ->assertJsonPath('meta.categories', ['Food', 'Transport']);
});

it('lists the last 30 days by default', function () {
    moneyEntry('expense', 100, '2026-10-10');
    moneyEntry('expense', 100, '2026-10-09');

    $this->getJson(route('money.index'))
        ->assertJsonPath('meta.from', '2026-10-10')
        ->assertJsonPath('meta.to', '2026-11-08')
        ->assertJsonCount(1, 'data');
});

it('changes only what is sent', function () {
    $entry = moneyEntry('expense', 2500, '2026-11-08', 'Food', 'Cheese');

    $this->patchJson(route('money.update', $entry), ['amount' => '26.75', 'category' => null])
        ->assertOk()
        ->assertJsonPath('data.amount', 26.75)
        ->assertJsonPath('data.category', null)
        ->assertJsonPath('data.title', 'Cheese');

    expect($entry->fresh()->amount_cents)->toBe(2675);
});

it('deletes an entry', function () {
    $entry = moneyEntry('expense', 2500, '2026-11-08');

    $this->deleteJson(route('money.destroy', $entry))->assertNoContent();

    expect(MoneyEntry::count())->toBe(0);
});
