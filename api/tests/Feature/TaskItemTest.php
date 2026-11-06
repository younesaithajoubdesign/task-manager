<?php

use App\Models\Task;
use App\Models\TaskItem;
use Database\Seeders\TaskManagerSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo('2026-11-07 21:00:00');
    $this->seed(TaskManagerSeeder::class);
    $this->task = Task::where('title', 'Logo design exercises')->firstOrFail();
});

function addItem(Task $task, string $title, ?string $link = null): array
{
    return test()->postJson(route('tasks.items.store', $task), array_filter(['title' => $title, 'link' => $link]))
        ->assertCreated()
        ->json('data');
}

it('adds items at the end of the list, with an optional link', function () {
    addItem($this->task, 'Perfect Days');
    $second = addItem($this->task, 'Paterson', 'https://example.com/paterson');

    expect($second)->toMatchArray(['title' => 'Paterson', 'link' => 'https://example.com/paterson', 'is_done' => false, 'position' => 2]);
});

it('adds https:// to a link typed without it, and drops an empty one', function () {
    expect(addItem($this->task, 'Article', 'example.com/read-me')['link'])->toBe('https://example.com/read-me');

    $this->postJson(route('tasks.items.store', $this->task), ['title' => 'No link', 'link' => '  '])
        ->assertCreated()
        ->assertJsonPath('data.link', null);
});

it('refuses an item without a title, or a link that is not a web address', function () {
    $this->postJson(route('tasks.items.store', $this->task), ['title' => ''])
        ->assertUnprocessable()->assertJsonValidationErrors('title');

    $this->postJson(route('tasks.items.store', $this->task), ['title' => 'x', 'link' => 'javascript:alert(1)'])
        ->assertUnprocessable()->assertJsonValidationErrors('link');

    foreach (['file://', 'file:///', 'file://server/a.pdf', 'ftp://example.com/a'] as $link) {
        $this->postJson(route('tasks.items.store', $this->task), ['title' => 'x', 'link' => $link])
            ->assertUnprocessable()->assertJsonValidationErrors('link');
    }
});

it('accepts a link to a file on the Mac', function () {
    $pdf = 'file:///Users/me/Downloads/Grammaire%20progressive%20du%20franc%CC%A7ais.pdf';

    expect(addItem($this->task, 'Grammaire', $pdf)['link'])->toBe($pdf);
});

it('turns a path pasted from Finder into a file link', function () {
    expect(addItem($this->task, 'Grammaire', '/Users/me/Downloads/Grammaire progressive.pdf')['link'])
        ->toBe('file:///Users/me/Downloads/Grammaire%20progressive.pdf')
        ->and(addItem($this->task, 'Encoded', '/Users/me/a%20b.pdf')['link'])
        ->toBe('file:///Users/me/a%20b.pdf')
        ->and(addItem($this->task, 'Spaces', 'file:///Users/me/a b.pdf')['link'])
        ->toBe('file:///Users/me/a%20b.pdf');
});

it('changes an item link to a file link', function () {
    $item = addItem($this->task, 'Book', 'example.com/book');

    $this->patchJson(route('items.update', $item['id']), ['link' => '/Users/me/book.pdf'])
        ->assertOk()->assertJsonPath('data.link', 'file:///Users/me/book.pdf');
});

it('ticks an item with the date, keeps that date when ticked again, and unticks it', function () {
    $item = addItem($this->task, 'Perfect Days');

    $this->patchJson(route('items.update', $item['id']), ['done' => true])
        ->assertOk()->assertJsonPath('data.is_done', true)->assertJsonPath('data.done_at', '2026-11-07T21:00:00+00:00');

    $this->travel(1)->day();
    $this->patchJson(route('items.update', $item['id']), ['done' => true])
        ->assertJsonPath('data.done_at', '2026-11-07T21:00:00+00:00');

    $this->patchJson(route('items.update', $item['id']), ['done' => false])
        ->assertJsonPath('data.is_done', false)->assertJsonPath('data.done_at', null);
});

it('lists to-do items in order, then done ones with the latest first', function () {
    $a = addItem($this->task, 'A');
    $b = addItem($this->task, 'B');
    addItem($this->task, 'C');
    $this->patchJson(route('items.update', $a['id']), ['done' => true]);
    $this->travel(1)->hour();
    $this->patchJson(route('items.update', $b['id']), ['done' => true]);

    $titles = collect($this->getJson(route('tasks.items.index', $this->task))->assertOk()->json('data'))->pluck('title');

    expect($titles->all())->toBe(['C', 'B', 'A']);
});

it('renames an item and removes its link', function () {
    $item = addItem($this->task, 'Typo', 'https://example.com');

    $this->patchJson(route('items.update', $item['id']), ['title' => 'Fixed', 'link' => null])
        ->assertOk()->assertJsonPath('data.title', 'Fixed')->assertJsonPath('data.link', null);
});

it('deletes an item, and the whole list goes with its task', function () {
    $item = addItem($this->task, 'One');
    addItem($this->task, 'Two');

    $this->deleteJson(route('items.destroy', $item['id']))->assertNoContent();
    expect(TaskItem::count())->toBe(1);

    $this->deleteJson(route('tasks.destroy', $this->task))->assertNoContent();
    expect(TaskItem::count())->toBe(0);
});

it('shows on each task row how many items are left', function () {
    $item = addItem($this->task, 'One');
    addItem($this->task, 'Two');
    $this->patchJson(route('items.update', $item['id']), ['done' => true]);

    $row = collect($this->getJson(route('tasks.index'))->json('data'))->firstWhere('id', $this->task->id);

    expect($row['open_items_count'])->toBe(1);
});

it('sits behind the password gate', function () {
    config(['gate.password_hash' => Hash::make('a test password')]);

    $this->getJson(route('tasks.items.index', $this->task))->assertUnauthorized();
});
