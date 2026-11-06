<?php

use App\Models\SubCategory;
use App\Models\Task;
use App\Models\TaskCompletion;
use Database\Seeders\TaskManagerSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaskManagerSeeder::class);
});

it('returns only open tasks when no filter is given, with the sub-category badge colour', function () {
    Task::first()->update(['status' => 'done', 'completed_at' => now()]);

    $response = $this->getJson(route('tasks.index'));

    $response->assertOk()
        ->assertJsonCount(Task::open()->count(), 'data')
        ->assertJsonStructure(['data' => [['id', 'title', 'is_done_today', 'tracked_seconds_today', 'sub_category' => ['title', 'color']]]]);

    $task = Task::with('subCategory')->find($response->json('data.0.id'));
    expect($response->json('data.0.sub_category.color'))->toBe($task->subCategory->color);
});

it('filters by status, sub-category and category', function () {
    Task::first()->update(['status' => 'done']);
    $this->getJson(route('tasks.index', ['status' => 'done']))->assertJsonCount(1, 'data');

    $sub = SubCategory::where('title', 'General')->whereRelation('category', 'title', 'Others')->firstOrFail();
    $this->getJson(route('tasks.index', ['sub_category_id' => $sub->id]))->assertJsonCount(2, 'data');
    $this->getJson(route('tasks.index', ['category_id' => $sub->category_id]))->assertJsonCount(2, 'data');
});

it('rejects an unknown status', function () {
    $this->getJson(route('tasks.index', ['status' => 'archived']))->assertUnprocessable();
});

it('orders overdue first, then by due date, then by importance', function () {
    $overdue = Task::where('title', 'Logo design exercises')->firstOrFail();
    $overdue->update(['due_at' => now()->subDays(3)]);

    $titles = collect($this->getJson(route('tasks.index'))->json('data'))->pluck('title');

    expect($titles->first())->toBe('Logo design exercises')
        ->and($titles->get(1))->toBe('Digital Marketing presentation')
        // Undated tasks come last, high importance before medium.
        ->and($titles->slice(2)->values()->all())->toBe(['French — 30 min', 'English — 30 min', 'Laracasts episode', 'Workout']);
});

it('marks a recurring task done today from task_completions without duplicating it', function () {
    $french = Task::where('title', 'like', 'French%')->firstOrFail();
    TaskCompletion::create(['task_id' => $french->id, 'date' => today()]);

    $tasks = collect($this->getJson(route('tasks.index'))->json('data'));

    expect($tasks->where('title', $french->title))->toHaveCount(1)
        ->and($tasks->firstWhere('id', $french->id)['is_done_today'])->toBeTrue();
});

it('counts today\'s tracked time server-side, including a running entry', function () {
    $task = Task::first();
    $task->timeEntries()->create(['started_at' => now()->subMinutes(10), 'ended_at' => now()->subMinutes(5), 'duration_seconds' => 300]);
    $task->timeEntries()->create(['started_at' => now()->subMinutes(2)]);
    $task->timeEntries()->create(['started_at' => now()->subDay(), 'ended_at' => now()->subDay()->addHour(), 'duration_seconds' => 3600]);

    $tracked = collect($this->getJson(route('tasks.index'))->json('data'))->firstWhere('id', $task->id)['tracked_seconds_today'];

    expect($tracked)->toBeGreaterThanOrEqual(420)->toBeLessThan(3600);
});

it('allows the Vite dev server origin via CORS', function () {
    $this->getJson(route('tasks.index'), ['Origin' => 'http://localhost:5173'])
        ->assertHeader('Access-Control-Allow-Origin', 'http://localhost:5173');
});
