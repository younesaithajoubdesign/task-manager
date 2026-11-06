<?php

use App\Models\Category;
use App\Models\SubCategory;
use App\Models\Task;
use App\Models\TaskCompletion;
use Database\Seeders\TaskManagerSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaskManagerSeeder::class);
});

describe('store', function () {
    it('creates a task from a title alone, in the sub-category of the last created task', function () {
        $lastUsed = Task::latest('id')->first()->sub_category_id;

        $response = $this->postJson(route('tasks.store'), ['title' => 'Buy charcoal pencils']);

        $response->assertCreated()
            ->assertJsonPath('data.title', 'Buy charcoal pencils')
            ->assertJsonPath('data.sub_category.id', $lastUsed)
            ->assertJsonPath('data.status', 'todo')
            ->assertJsonPath('data.is_done_today', false);

        $this->assertDatabaseHas('tasks', ['title' => 'Buy charcoal pencils', 'sub_category_id' => $lastUsed]);
    });

    it('falls back to the first sub-category in sidebar order when no task exists yet', function () {
        Task::query()->delete();
        $first = Category::orderBy('position')->first()->subCategories()->first();

        $this->postJson(route('tasks.store'), ['title' => 'First ever task'])
            ->assertCreated()
            ->assertJsonPath('data.sub_category.id', $first->id);
    });

    it('uses the sub-category sent by the client', function () {
        $sub = SubCategory::where('title', 'Chess')->orWhereRelation('category', 'title', 'Chess')->firstOrFail();

        $this->postJson(route('tasks.store'), ['title' => 'Endgame drills', 'sub_category_id' => $sub->id])
            ->assertCreated()
            ->assertJsonPath('data.sub_category.id', $sub->id);
    });

    it('returns 422 when the title is blank', function () {
        $this->postJson(route('tasks.store'), ['title' => '   '])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title' => 'The title field is required.']);
    });

    it('returns 422 asking for a category when no sub-category exists', function () {
        Category::query()->delete();

        $this->postJson(route('tasks.store'), ['title' => 'Orphan'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sub_category_id' => 'Create a category and a sub-category first.']);
    });

    it('stores a date-only due date as 23:59 that day', function () {
        $this->postJson(route('tasks.store'), ['title' => 'Hand in poster', 'due_at' => '2026-12-01'])
            ->assertCreated();

        expect(Task::where('title', 'Hand in poster')->first()->due_at->format('Y-m-d H:i'))->toBe('2026-12-01 23:59');
    });
});

describe('update', function () {
    it('changes only the fields sent', function () {
        $task = Task::where('title', 'Logo design exercises')->firstOrFail();

        $this->patchJson(route('tasks.update', $task), ['title' => 'Logo exercises, round 2'])
            ->assertOk()
            ->assertJsonPath('data.title', 'Logo exercises, round 2')
            ->assertJsonPath('data.planned_minutes', 90);
    });

    it('sets completed_at when marked done and clears it when reopened', function () {
        $task = Task::where('title', 'Digital Marketing presentation')->firstOrFail();

        $this->patchJson(route('tasks.update', $task), ['status' => 'done'])->assertOk();
        expect($task->fresh()->completed_at)->not->toBeNull();

        $this->patchJson(route('tasks.update', $task), ['status' => 'todo'])->assertOk();
        expect($task->fresh()->completed_at)->toBeNull();
    });

    it('returns 422 when a weekly repeat has no days', function () {
        $task = Task::where('repeat', null)->firstOrFail();

        $this->patchJson(route('tasks.update', $task), ['repeat' => 'weekly'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('repeat_days');
    });

    it('clears repeat_days when a weekly task becomes daily', function () {
        $task = Task::where('repeat', 'weekly')->firstOrFail();

        $this->patchJson(route('tasks.update', $task), ['repeat' => 'daily'])
            ->assertOk()
            ->assertJsonPath('data.repeat_days', null);
    });

    it('returns 422 for an invalid link or importance', function () {
        $task = Task::first();

        $this->patchJson(route('tasks.update', $task), ['link' => 'not a url', 'importance' => 'urgent'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['link', 'importance']);
    });

    it('returns 404 for an unknown task', function () {
        $this->patchJson('/api/tasks/999999', ['title' => 'Ghost'])->assertNotFound();
    });
});

describe('destroy', function () {
    it('deletes the task and its completions', function () {
        $task = Task::where('repeat', 'daily')->firstOrFail();
        TaskCompletion::create(['task_id' => $task->id, 'date' => today()]);

        $this->deleteJson(route('tasks.destroy', $task))->assertNoContent();

        $this->assertModelMissing($task);
        $this->assertDatabaseMissing('task_completions', ['task_id' => $task->id]);
    });
});
