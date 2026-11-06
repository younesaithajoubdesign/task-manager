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

describe('categories', function () {
    it('creates a category last in the sidebar, with a General sub-category ready for tasks', function () {
        $response = $this->postJson(route('categories.store'), ['title' => 'Music'])
            ->assertCreated()
            ->assertJsonPath('data.title', 'Music')
            ->assertJsonPath('data.sub_categories.0.title', 'General');

        expect(Category::orderByDesc('position')->first()->id)->toBe($response->json('data.id'));
    });

    it('returns 422 for a blank title', function () {
        $this->postJson(route('categories.store'), ['title' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title' => 'The title field is required.']);
    });

    it('renames a category', function () {
        $chess = Category::where('title', 'Chess')->firstOrFail();

        $this->patchJson(route('categories.update', $chess), ['title' => 'Chess & Go'])
            ->assertOk()
            ->assertJsonPath('data.title', 'Chess & Go');
    });

    it('deletes a category without tasks straight away', function () {
        $films = Category::where('title', 'Films')->firstOrFail();

        $this->deleteJson(route('categories.destroy', $films))->assertNoContent();

        $this->assertModelMissing($films);
        $this->assertDatabaseMissing('sub_categories', ['category_id' => $films->id]);
    });

    it('returns 409 with the counts when the category still holds tasks', function () {
        $study = Category::where('title', 'Study')->firstOrFail();
        Task::where('title', 'Digital Marketing presentation')->update(['status' => 'done']);

        $this->deleteJson(route('categories.destroy', $study))
            ->assertConflict()
            ->assertJsonPath('tasks_count', 2)
            ->assertJsonPath('open_tasks_count', 1)
            ->assertJsonPath('sub_categories_count', 3);

        $this->assertModelExists($study);
    });

    it('deletes the category, its sub-categories and tasks when forced', function () {
        $others = Category::where('title', 'Others')->firstOrFail();
        $french = Task::where('title', 'French — 30 min')->firstOrFail();
        TaskCompletion::create(['task_id' => $french->id, 'date' => today()]);

        $this->deleteJson(route('categories.destroy', [$others, 'force' => 1]))->assertNoContent();

        $this->assertModelMissing($others);
        $this->assertModelMissing($french);
        $this->assertDatabaseMissing('task_completions', ['task_id' => $french->id]);
    });
});

describe('sub-categories', function () {
    it('creates a sub-category last in its category with a palette colour', function () {
        $study = Category::where('title', 'Study')->firstOrFail();

        $response = $this->postJson(route('sub-categories.store'), ['category_id' => $study->id, 'title' => 'Thesis'])
            ->assertCreated()
            ->assertJsonPath('data.title', 'Thesis')
            ->assertJsonPath('data.position', 3);

        expect(SubCategory::PALETTE)->toContain($response->json('data.color'));
    });

    it('stores a chosen colour in lower case', function () {
        $study = Category::where('title', 'Study')->firstOrFail();

        $this->postJson(route('sub-categories.store'), ['category_id' => $study->id, 'title' => 'Thesis', 'color' => '#AABBCC'])
            ->assertCreated()
            ->assertJsonPath('data.color', '#aabbcc');
    });

    it('returns 422 for a colour that is not a hex value', function () {
        $sub = SubCategory::first();

        $this->patchJson(route('sub-categories.update', $sub), ['color' => 'pink'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['color' => 'The colour must be a hex value like #3b82f6.']);
    });

    it('recolours a sub-category, which recolours its tasks\' badges', function () {
        $laravel = SubCategory::where('title', 'Laravel')->firstOrFail();

        $this->patchJson(route('sub-categories.update', $laravel), ['color' => '#123456'])->assertOk();

        $laracasts = collect($this->getJson(route('tasks.index'))->json('data'))->firstWhere('title', 'Laracasts episode');
        expect($laracasts['sub_category']['color'])->toBe('#123456');
    });

    it('returns 409 when the sub-category still holds tasks, and deletes them when forced', function () {
        $exercises = SubCategory::where('title', 'Exercises')->firstOrFail();

        $this->deleteJson(route('sub-categories.destroy', $exercises))
            ->assertConflict()
            ->assertJsonPath('tasks_count', 1);

        $this->deleteJson(route('sub-categories.destroy', [$exercises, 'force' => 1]))->assertNoContent();

        $this->assertModelMissing($exercises);
        $this->assertDatabaseMissing('tasks', ['title' => 'Logo design exercises']);
    });

    it('deletes an empty sub-category straight away', function () {
        $react = SubCategory::where('title', 'React')->firstOrFail();

        $this->deleteJson(route('sub-categories.destroy', $react))->assertNoContent();

        $this->assertModelMissing($react);
    });
});
