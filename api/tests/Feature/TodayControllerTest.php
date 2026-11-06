<?php

use App\Models\SubCategory;
use App\Models\Task;
use App\Models\TaskCompletion;
use Database\Seeders\TaskManagerSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Monday 9 November 2026, 11:00 — Laracasts (Mon/Wed/Fri) occurs, Workout (Tue/Thu/Sat) does not.
    $this->travelTo('2026-11-09 11:00:00');
    $this->seed(TaskManagerSeeder::class);
    $this->sub = SubCategory::first();
});

function titlesIn(array $group): array
{
    return array_column($group, 'title');
}

it('returns overdue, due today and today\'s habits in one call', function () {
    Task::create(['sub_category_id' => $this->sub->id, 'title' => 'Late essay', 'due_at' => '2026-11-07 23:59']);
    Task::create(['sub_category_id' => $this->sub->id, 'title' => 'Poster due tonight', 'due_at' => '2026-11-09 23:59']);
    Task::create(['sub_category_id' => $this->sub->id, 'title' => 'Next week', 'due_at' => '2026-11-16 23:59']);

    $data = $this->getJson(route('today'))->assertOk()->json('data');

    expect($data['date'])->toBe('2026-11-09')
        ->and(titlesIn($data['overdue']))->toBe(['Late essay'])
        ->and(titlesIn($data['due_today']))->toBe(['Poster due tonight'])
        ->and(titlesIn($data['habits']))->toBe(['French — 30 min', 'English — 30 min', 'Laracasts episode']);
});

it('lists a task already past its due time today as overdue only', function () {
    Task::create(['sub_category_id' => $this->sub->id, 'title' => 'Morning call', 'due_at' => '2026-11-09 09:00']);

    $data = $this->getJson(route('today'))->json('data');

    expect(titlesIn($data['overdue']))->toBe(['Morning call'])
        ->and($data['due_today'])->toBe([]);
});

it('leaves out completed one-off tasks', function () {
    Task::create(['sub_category_id' => $this->sub->id, 'title' => 'Done already', 'due_at' => '2026-11-09 23:59', 'status' => 'done']);
    Task::create(['sub_category_id' => $this->sub->id, 'title' => 'Done late', 'due_at' => '2026-11-01 23:59', 'status' => 'done']);

    $data = $this->getJson(route('today'))->json('data');

    expect($data['overdue'])->toBe([])->and($data['due_today'])->toBe([]);
});

it('keeps a habit ticked today in the list, marked done', function () {
    $french = Task::where('title', 'French — 30 min')->firstOrFail();
    TaskCompletion::create(['task_id' => $french->id, 'date' => '2026-11-09']);

    $habits = collect($this->getJson(route('today'))->json('data.habits'));

    expect($habits->firstWhere('id', $french->id)['is_done_today'])->toBeTrue()
        ->and($habits->where('id', '!=', $french->id)->pluck('is_done_today')->unique()->all())->toBe([false]);
});

it('never puts a recurring task with a due date in the dated groups', function () {
    Task::where('title', 'French — 30 min')->update(['due_at' => '2026-11-01 23:59']);

    $data = $this->getJson(route('today'))->json('data');

    expect(titlesIn($data['overdue']))->not->toContain('French — 30 min')
        ->and(titlesIn($data['habits']))->toContain('French — 30 min');
});

it('returns three empty groups when nothing is due and no habit occurs', function () {
    Task::query()->delete();

    $this->getJson(route('today'))
        ->assertOk()
        ->assertJsonPath('data.overdue', [])
        ->assertJsonPath('data.due_today', [])
        ->assertJsonPath('data.habits', []);
});
