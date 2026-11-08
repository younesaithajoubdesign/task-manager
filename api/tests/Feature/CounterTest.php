<?php

use App\Models\CounterReset;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo('2026-11-08 12:00:00');
});

it('shows a counter that has not started yet', function () {
    $this->getJson(route('counter.show'))
        ->assertOk()
        ->assertJsonPath('data.started_at', null)
        ->assertJsonPath('data.days', 0)
        ->assertJsonPath('data.best_days', 0)
        ->assertJsonPath('data.resets', []);
});

it('starts counting from a moment in the past and counts whole days', function () {
    $this->postJson(route('counter.reset'), ['reset_at' => '2026-10-16 21:40:00'])
        ->assertCreated()
        ->assertJsonPath('data.started_at', '2026-10-16T21:40:00+00:00')
        ->assertJsonPath('data.days', 22) // 22 days and 14 hours
        ->assertJsonPath('data.resets_count', 0)
        ->assertJsonPath('data.resets.0.run_days', null);
});

it('restarts from zero now, records the run it ended, and keeps the best one', function () {
    CounterReset::create(['reset_at' => '2026-09-01 08:00:00']);
    CounterReset::create(['reset_at' => '2026-10-11 09:00:00']); // ended a 40-day run

    $this->postJson(route('counter.reset'))
        ->assertCreated()
        ->assertJsonPath('data.started_at', '2026-11-08T12:00:00+00:00')
        ->assertJsonPath('data.days', 0)
        ->assertJsonPath('data.best_days', 40)
        ->assertJsonPath('data.resets_count', 2)
        ->assertJsonPath('data.resets.0.reset_at', '2026-11-08T12:00:00+00:00')
        ->assertJsonPath('data.resets.0.run_days', 28)
        ->assertJsonPath('data.resets.1.run_days', 40);
});

it('counts the current run as the best when it is the longest', function () {
    CounterReset::create(['reset_at' => '2026-10-01 12:00:00']);
    CounterReset::create(['reset_at' => '2026-10-04 12:00:00']); // 3-day run

    $this->getJson(route('counter.show'))
        ->assertJsonPath('data.days', 35)
        ->assertJsonPath('data.best_days', 35);
});

it('refuses a reset in the future or that is not a date', function (mixed $resetAt) {
    $this->postJson(route('counter.reset'), ['reset_at' => $resetAt])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('reset_at');

    expect(CounterReset::count())->toBe(0);
})->with(['tomorrow' => '2026-11-09 08:00:00', 'not a date' => 'yesterday-ish']);

it('removes a reset logged by mistake, joining the two runs', function () {
    CounterReset::create(['reset_at' => '2026-10-01 12:00:00']);
    $mistake = CounterReset::create(['reset_at' => '2026-11-07 12:00:00']);

    $this->deleteJson(route('counter.resets.destroy', $mistake))
        ->assertOk()
        ->assertJsonPath('data.started_at', '2026-10-01T12:00:00+00:00')
        ->assertJsonPath('data.days', 38);

    expect(CounterReset::count())->toBe(1);
});
