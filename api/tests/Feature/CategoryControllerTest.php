<?php

use App\Models\Category;
use App\Models\SubCategory;
use App\Models\Task;
use Database\Seeders\TaskManagerSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaskManagerSeeder::class);
});

it('returns categories and their sub-categories in sidebar order', function () {
    Category::where('title', 'Study')->update(['position' => 99]);
    SubCategory::where('title', 'Projects')->update(['position' => 99]);

    $data = $this->getJson(route('categories.index'))->assertOk()->json('data');

    expect(array_column($data, 'title'))->toBe(['Development', 'CHI', 'Projects', 'Sport', 'Chess', 'Films', 'Others', 'Study'])
        ->and(array_column(end($data)['sub_categories'], 'title'))->toBe(['Exercises', 'Presentations', 'Projects']);
});

it('carries each sub-category colour for the badges', function () {
    $sub = SubCategory::where('title', 'Laravel')->firstOrFail();

    $development = collect($this->getJson(route('categories.index'))->json('data'))->firstWhere('title', 'Development');

    expect(collect($development['sub_categories'])->firstWhere('id', $sub->id)['color'])->toBe($sub->color);
});

it('counts only open tasks per category and per sub-category', function () {
    Task::where('title', 'Digital Marketing presentation')->update(['status' => 'done']);

    $study = collect($this->getJson(route('categories.index'))->json('data'))->firstWhere('title', 'Study');
    $subs = collect($study['sub_categories'])->keyBy('title');

    expect($study['open_tasks_count'])->toBe(1)
        ->and($subs['Exercises']['open_tasks_count'])->toBe(1)
        ->and($subs['Presentations']['open_tasks_count'])->toBe(0);
});
