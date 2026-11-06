<?php

use App\Models\Task;
use App\Models\TaskCompletion;
use App\Models\TimeEntry;
use Database\Seeders\TaskManagerSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Monday 9 November 2026.
    $this->travelTo('2026-11-09 18:00:00');
    $this->seed(TaskManagerSeeder::class);
    $this->logo = Task::where('title', 'Logo design exercises')->firstOrFail(); // Study
    $this->laracastsTask = Task::where('title', 'Laracasts episode')->firstOrFail(); // Development, Mon/Wed/Fri
    $this->french = Task::where('title', 'French — 30 min')->firstOrFail(); // Others, daily

    // Habits seeded "today" would only be scheduled from today on.
    Task::query()->recurring()->update(['created_at' => '2026-10-01 00:00:00']);
});

function entry(Task $task, string $startedAt, ?int $seconds): TimeEntry
{
    return TimeEntry::create([
        'task_id' => $task->id,
        'started_at' => $startedAt,
        'ended_at' => $seconds === null ? null : now()->parse($startedAt)->addSeconds($seconds),
        'duration_seconds' => $seconds,
    ]);
}

function day(array $days, string $date): int
{
    return collect($days)->firstWhere('date', $date)['seconds'];
}

function category(array $categories, string $title): int
{
    return collect($categories)->firstWhere('title', $title)['seconds'];
}

function habit(array $habits, Task $task): array
{
    return collect($habits)->firstWhere('id', $task->id);
}

it('covers the last 30 days by default, one row per day', function () {
    $data = $this->getJson(route('stats'))->assertOk()->json('data');

    expect($data['from'])->toBe('2026-10-11')
        ->and($data['to'])->toBe('2026-11-09')
        ->and($data['days'])->toHaveCount(30)
        ->and($data['days'][0])->toBe(['date' => '2026-10-11', 'seconds' => 0])
        ->and($data['total_seconds'])->toBe(0);
});

it('sums tracked seconds per day and per category', function () {
    entry($this->logo, '2026-11-02 09:00:00', 1200);
    entry($this->logo, '2026-11-02 14:00:00', 600);
    entry($this->laracastsTask, '2026-11-03 20:00:00', 900);

    $data = $this->getJson(route('stats', ['from' => '2026-11-01', 'to' => '2026-11-07']))->json('data');

    expect(day($data['days'], '2026-11-02'))->toBe(1800)
        ->and(day($data['days'], '2026-11-03'))->toBe(900)
        ->and($data['total_seconds'])->toBe(2700)
        ->and(category($data['categories'], 'Study'))->toBe(1800)
        ->and(category($data['categories'], 'Development'))->toBe(900)
        ->and(category($data['categories'], 'Sport'))->toBe(0)
        ->and($data['categories'][0]['title'])->toBe('Study');
});

it('counts an entry on the day it started, even when it runs past midnight', function () {
    entry($this->logo, '2026-11-02 23:30:00', 3600);

    $data = $this->getJson(route('stats', ['from' => '2026-11-02', 'to' => '2026-11-03']))->json('data');

    expect(day($data['days'], '2026-11-02'))->toBe(3600)
        ->and(day($data['days'], '2026-11-03'))->toBe(0);
});

it('leaves out entries outside the window', function () {
    entry($this->logo, '2026-10-31 23:59:59', 100);
    entry($this->logo, '2026-11-08 00:00:00', 200);

    $data = $this->getJson(route('stats', ['from' => '2026-11-01', 'to' => '2026-11-07']))->json('data');

    expect($data['total_seconds'])->toBe(0);
});

it('counts a running entry up to now', function () {
    entry($this->logo, '2026-11-09 17:15:00', null);

    $data = $this->getJson(route('stats'))->json('data');

    expect(day($data['days'], '2026-11-09'))->toBe(45 * 60)
        ->and(category($data['categories'], 'Study'))->toBe(45 * 60);
});

it('gives each habit its ticked days, scheduled days and streak', function () {
    foreach (['2026-11-04', '2026-11-06', '2026-11-09'] as $date) {
        TaskCompletion::create(['task_id' => $this->laracastsTask->id, 'date' => $date]);
    }

    $data = $this->getJson(route('stats', ['from' => '2026-11-02', 'to' => '2026-11-09']))->json('data');
    $laracasts = habit($data['habits'], $this->laracastsTask);

    // Scheduled Mon 2, Wed 4, Fri 6, Mon 9; Monday 2 was missed.
    expect($laracasts['completed_dates'])->toBe(['2026-11-04', '2026-11-06', '2026-11-09'])
        ->and($laracasts['completed_count'])->toBe(3)
        ->and($laracasts['scheduled_count'])->toBe(4)
        ->and($laracasts['streak'])->toBe(3)
        // Mon 2 → Mon 9, one state per day.
        ->and($laracasts['days'])->toBe(['missed', 'off', 'done', 'off', 'done', 'off', 'off', 'done'])
        ->and($laracasts['color'])->toBe($this->laracastsTask->subCategory->color)
        ->and(collect($data['habits'])->pluck('id'))->not->toContain($this->logo->id);
});

it('measures the streak as of the end of the window', function () {
    foreach (['2026-11-01', '2026-11-02', '2026-11-03', '2026-11-08'] as $date) {
        TaskCompletion::create(['task_id' => $this->french->id, 'date' => $date]);
    }

    $data = $this->getJson(route('stats', ['from' => '2026-11-01', 'to' => '2026-11-03']))->json('data');

    expect(habit($data['habits'], $this->french)['streak'])->toBe(3);
});

it('does not count days before a habit existed, or after today, as scheduled', function () {
    $this->french->forceFill(['created_at' => '2026-11-07 12:00:00'])->save();

    $data = $this->getJson(route('stats', ['from' => '2026-11-01', 'to' => '2026-11-30']))->json('data');

    // 7, 8 and 9 November.
    $french = habit($data['habits'], $this->french);

    expect($french['scheduled_count'])->toBe(3)
        ->and(array_slice($french['days'], 5, 4))->toBe(['off', 'missed', 'missed', 'open'])
        ->and($french['days'][9])->toBe('off');
});

it('rejects a malformed or inverted window', function (array $query, string $field) {
    $this->getJson(route('stats', $query))->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([
    'not a date' => [['from' => '1 November'], 'from'],
    'end before start' => [['from' => '2026-11-09', 'to' => '2026-11-01'], 'to'],
    'more than 366 days' => [['from' => '2025-01-01', 'to' => '2026-11-09'], 'from'],
]);

it('sits behind the password gate', function () {
    config(['gate.password_hash' => Hash::make('a test password')]);

    $this->getJson(route('stats'))->assertUnauthorized();
});
