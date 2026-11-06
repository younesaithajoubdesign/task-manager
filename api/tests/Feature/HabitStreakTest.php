<?php

use App\Models\Task;
use App\Models\TaskCompletion;
use Database\Seeders\TaskManagerSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Monday 9 November 2026.
    $this->travelTo('2026-11-09 18:00:00');
    $this->seed(TaskManagerSeeder::class);
    $this->french = Task::where('title', 'French — 30 min')->firstOrFail(); // daily
    $this->laracasts = Task::where('title', 'Laracasts episode')->firstOrFail(); // Mon/Wed/Fri
});

function tick(Task $task, string ...$dates): void
{
    foreach ($dates as $date) {
        TaskCompletion::create(['task_id' => $task->id, 'date' => $date]);
    }
}

function streakOf(Task $task): int
{
    return collect(test()->getJson(route('today'))->json('data.habits'))->firstWhere('id', $task->id)['streak'];
}

it('counts consecutive days ending today for a daily habit', function () {
    tick($this->french, '2026-11-07', '2026-11-08', '2026-11-09');

    expect(streakOf($this->french))->toBe(3);
});

it('keeps yesterday\'s streak alive while today is not ticked yet', function () {
    tick($this->french, '2026-11-07', '2026-11-08');

    expect(streakOf($this->french))->toBe(2);
});

it('stops at the first missed day', function () {
    tick($this->french, '2026-11-05', '2026-11-06', '2026-11-08', '2026-11-09');

    expect(streakOf($this->french))->toBe(2);
});

it('counts only scheduled days for a weekly habit', function () {
    // Fri 6, Wed 4, Mon 2 November: three scheduled days in a row, gaps in between are not missed days.
    tick($this->laracasts, '2026-11-02', '2026-11-04', '2026-11-06');

    expect(streakOf($this->laracasts))->toBe(3);
});

it('returns 0 for a habit never ticked', function () {
    expect(streakOf($this->french))->toBe(0);
});

it('ticking and unticking a habit only touches task_completions', function () {
    $this->postJson(route('tasks.complete', $this->french))->assertJsonPath('data.is_done_today', true);
    expect(streakOf($this->french))->toBe(1);

    $this->postJson(route('tasks.uncomplete', $this->french))->assertJsonPath('data.is_done_today', false);

    expect($this->french->completions()->count())->toBe(0)
        ->and($this->french->fresh()->status)->toBe('todo')
        ->and(streakOf($this->french))->toBe(0);
});
