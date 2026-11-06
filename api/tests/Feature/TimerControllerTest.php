<?php

use App\Models\Task;
use App\Models\TimeEntry;
use Database\Seeders\TaskManagerSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo('2026-11-09 10:00:00');
    $this->seed(TaskManagerSeeder::class);
    $this->task = Task::where('title', 'Logo design exercises')->firstOrFail();
    $this->other = Task::where('title', 'Digital Marketing presentation')->firstOrFail();
});

describe('start', function () {
    it('opens a time entry and sets the task to doing', function () {
        $this->postJson(route('timer.start', $this->task))
            ->assertOk()
            ->assertJsonPath('data.is_running', true)
            ->assertJsonPath('data.task.status', 'doing')
            ->assertJsonPath('data.planned_seconds', 90 * 60);

        expect($this->task->timeEntries()->whereNull('ended_at')->count())->toBe(1);
    });

    it('closes any other running timer first, so only one runs', function () {
        $this->postJson(route('timer.start', $this->other));
        $this->travel(5)->minutes();

        $this->postJson(route('timer.start', $this->task))->assertOk();

        expect(TimeEntry::whereNull('ended_at')->pluck('task_id')->all())->toBe([$this->task->id])
            ->and($this->other->timeEntries()->first()->duration_seconds)->toBe(300);
    });

    it('does not open a second entry when the task is already running', function () {
        $this->postJson(route('timer.start', $this->task));
        $this->postJson(route('timer.start', $this->task));

        expect($this->task->timeEntries()->count())->toBe(1);
    });
});

it('pauses by closing the entry and resumes with a new one, staying doing', function () {
    $this->postJson(route('timer.start', $this->task));
    $this->travel(10)->minutes();

    $this->postJson(route('timer.pause', $this->task))
        ->assertJsonPath('data.is_running', false)
        ->assertJsonPath('data.task.status', 'doing')
        ->assertJsonPath('data.tracked_seconds_today', 600);

    $this->travel(30)->minutes();
    $this->postJson(route('timer.resume', $this->task))->assertJsonPath('data.is_running', true);
    $this->travel(2)->minutes();

    $this->getJson(route('timer.running'))->assertJsonPath('data.tracked_seconds_today', 720);
    expect($this->task->timeEntries()->count())->toBe(2);
});

it('stops by closing the entry and returning the task to todo, not done', function () {
    $this->postJson(route('timer.start', $this->task));
    $this->travel(3)->minutes();

    $this->postJson(route('timer.stop', $this->task))
        ->assertJsonPath('data.is_running', false)
        ->assertJsonPath('data.task.status', 'todo');

    expect($this->task->timeEntries()->first()->duration_seconds)->toBe(180);
});

describe('running', function () {
    it('returns null when no timer runs', function () {
        $this->getJson(route('timer.running'))->assertOk()->assertExactJson(['data' => null]);
    });

    it('computes elapsed time from started_at, so a reload loses nothing', function () {
        $this->postJson(route('timer.start', $this->task));
        $this->travel(25)->minutes();

        $this->getJson(route('timer.running'))
            ->assertJsonPath('data.task.id', $this->task->id)
            ->assertJsonPath('data.is_running', true)
            ->assertJsonPath('data.current_entry_seconds', 1500)
            ->assertJsonPath('data.tracked_seconds_today', 1500);
    });
});

it('resets today\'s time to zero and keeps a running timer running', function () {
    $this->postJson(route('timer.start', $this->task));
    $this->travel(20)->minutes();

    $this->postJson(route('timer.reset', $this->task))
        ->assertJsonPath('data.is_running', true)
        ->assertJsonPath('data.tracked_seconds_today', 0);

    expect($this->task->timeEntries()->count())->toBe(1);
});

describe('complete', function () {
    it('closes the running entry and marks a one-off task done', function () {
        $this->postJson(route('timer.start', $this->task));
        $this->travel(5)->minutes();

        $this->postJson(route('tasks.complete', $this->task))
            ->assertOk()
            ->assertJsonPath('data.status', 'done')
            ->assertJsonPath('data.is_done_today', true);

        expect($this->task->fresh()->completed_at)->not->toBeNull()
            ->and(TimeEntry::whereNull('ended_at')->exists())->toBeFalse();
    });

    it('ticks a habit for today once, without a new task row or status change', function () {
        $french = Task::where('title', 'French — 30 min')->firstOrFail();
        $tasksBefore = Task::count();

        $this->postJson(route('tasks.complete', $french))->assertJsonPath('data.is_done_today', true);
        $this->postJson(route('tasks.complete', $french))->assertOk();

        expect($french->completions()->count())->toBe(1)
            ->and(Task::count())->toBe($tasksBefore)
            ->and($french->fresh()->status)->toBe('todo');
    });

    it('undoes a completion for a habit and for a one-off task', function () {
        $french = Task::where('title', 'French — 30 min')->firstOrFail();
        $this->postJson(route('tasks.complete', $french));
        $this->postJson(route('tasks.complete', $this->task));

        $this->postJson(route('tasks.uncomplete', $french))->assertJsonPath('data.is_done_today', false);
        $this->postJson(route('tasks.uncomplete', $this->task))->assertJsonPath('data.status', 'todo');

        expect($french->completions()->count())->toBe(0);
    });
});

it('returns 404 when timing an unknown task', function () {
    $this->postJson('/api/tasks/999999/timer/start')->assertNotFound();
});
