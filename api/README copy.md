# Task Manager — database layer

Copy these folders into your Laravel app (`api/`), keeping the same structure:

```
database/migrations/   5 files
database/seeders/      TaskManagerSeeder.php
app/Models/            5 files
```

If you already ran `php artisan make:model ... -m` yesterday, delete the empty
files it generated first — these replace them.

## Register the seeder

In `database/seeders/DatabaseSeeder.php`, inside `run()`:

```php
$this->call(TaskManagerSeeder::class);
```

## Run it

```bash
php artisan migrate:fresh --seed
```

`migrate:fresh` drops everything first — fine now, never again once you have
real data in there.

## Check it worked

```bash
php artisan tinker
>>> App\Models\Category::with('subCategories')->get()->pluck('title');
>>> App\Models\Task::recurring()->pluck('title');
>>> App\Models\Task::first()->subCategory->color;
```

## Notes on the schema

- **`time_entries`** is the table that makes the timer real. Starting the timer
  inserts a row with `started_at` and `ended_at = null`. Pausing closes it
  (`stop()` freezes `duration_seconds`). Resuming opens a new row. The elapsed
  time is always computed from `started_at`, never held in JavaScript — so a
  refresh, a closed tab or a reboot never loses a session.

- **Recurring tasks are never duplicated.** One row in `tasks` with
  `repeat = 'daily'`, and one row in `task_completions` per day you tick it.
  `$task->isDoneOn(today())` and `$task->occursOn(today())` are what your
  "Today" view will call.

- **`color` lives on the sub-category**, not the task. So every task in
  "Exercises" carries the same badge colour and the colour actually means
  something, instead of being decorative.

- **`importance`** is stored but nothing in your mockups shows it. Decide what
  it does — sort order, or a marker on the card — or drop the column before you
  have data in it.
