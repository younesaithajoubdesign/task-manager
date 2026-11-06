<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

class Task extends Model
{
    protected $fillable = [
        'sub_category_id', 'title', 'notes', 'link',
        'type', 'planned_minutes', 'importance', 'status',
        'due_at', 'completed_at', 'repeat', 'repeat_days', 'position',
    ];

    /** How far back a streak is counted; keeps it one bounded query. */
    public const STREAK_WINDOW_DAYS = 365;

    /**
     * Every task row shows how many checklist items are left
     * (open_items_count): one cheap sub-query, no extra round trip.
     *
     * @var list<string>
     */
    protected $withCount = ['openItems'];

    /**
     * Consecutive completed occurrences, set by attachStreaks() for the
     * Today screen. A declared property, not an attribute: never saved.
     */
    public ?int $streakCount = null;

    /**
     * Mirrors the column defaults in the migration, so a freshly created
     * task (quick capture sends only a title) has them without a re-query.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'type' => 'open',
        'importance' => 'medium',
        'status' => 'todo',
        'position' => 0,
    ];

    protected $casts = [
        'due_at' => 'datetime',
        'completed_at' => 'datetime',
        'repeat_days' => 'array',
    ];

    /**
     * Keep completed_at in step with status, whichever path changes it.
     */
    protected static function booted(): void
    {
        static::saving(function (Task $task) {
            if ($task->isDirty('status')) {
                $task->completed_at = $task->status === 'done'
                    ? ($task->completed_at ?? now())
                    : null;
            }
        });
    }

    /**
     * Relations a task row needs (see TaskResource), constrained to today
     * so is_done_today and tracked_seconds_today cost no extra queries.
     *
     * @return array<string|int, mixed>
     */
    public static function listRelations(): array
    {
        $today = today()->toDateString();

        return [
            'subCategory',
            'completions' => fn ($query) => $query->whereDate('date', $today),
            'timeEntries' => fn ($query) => $query->whereDate('started_at', $today),
        ];
    }

    // ---- Relations ----------------------------------------------------

    public function subCategory(): BelongsTo
    {
        return $this->belongsTo(SubCategory::class);
    }

    public function timeEntries(): HasMany
    {
        return $this->hasMany(TimeEntry::class);
    }

    /** The entry currently running, if the timer is on. */
    public function runningEntry(): HasOne
    {
        return $this->hasOne(TimeEntry::class)->whereNull('ended_at');
    }

    public function completions(): HasMany
    {
        return $this->hasMany(TaskCompletion::class);
    }

    /** The task's checklist (films, articles, subjects…). */
    public function items(): HasMany
    {
        return $this->hasMany(TaskItem::class);
    }

    public function openItems(): HasMany
    {
        return $this->items()->whereNull('done_at');
    }

    // ---- Helpers ------------------------------------------------------

    public function isRecurring(): bool
    {
        return $this->repeat !== null;
    }

    /** Is this task done for the given day? Handles both one-off and recurring. */
    public function isDoneOn(Carbon $date): bool
    {
        if (! $this->isRecurring()) {
            return $this->status === 'done';
        }

        return $this->completions()
            ->whereDate('date', $date->toDateString())
            ->exists();
    }

    /** Does a recurring task fall on the given day? */
    public function occursOn(Carbon $date): bool
    {
        return match ($this->repeat) {
            'daily' => true,
            'weekly' => in_array($date->dayOfWeekIso, $this->repeat_days ?? [], true),
            default => false,
        };
    }

    /** Total tracked seconds, optionally for one day only. */
    public function trackedSeconds(?Carbon $date = null): int
    {
        return (int) $this->timeEntries()
            ->when($date, fn ($q) => $q->whereDate('started_at', $date->toDateString()))
            ->sum('duration_seconds');
    }

    /**
     * The three groups of the Today screen (SPEC §5.2). Each task appears
     * in exactly one group: a one-off task already past its due time today
     * counts as overdue, and recurring tasks only ever appear as habits.
     *
     * @return array{overdue: Collection<int, Task>, due_today: Collection<int, Task>, habits: Collection<int, Task>}
     */
    public static function today(?Carbon $date = null): array
    {
        $date ??= today();

        $oneOff = fn () => static::query()
            ->whereNull('repeat')
            ->with(static::listRelations())
            ->orderedForList();

        return [
            'overdue' => $oneOff()->overdue()->get(),
            'due_today' => $oneOff()->dueOn($date)->where('due_at', '>=', now())->get(),
            'habits' => static::query()
                ->recurring()
                ->with(static::listRelations())
                ->orderedForList()
                ->get()
                ->filter(fn (Task $task) => $task->occursOn($date))
                ->values()
                ->tap(fn (Collection $habits) => static::attachStreaks($habits, $date)),
        ];
    }

    /**
     * Set streakCount on each habit with one query for all of them.
     *
     * @param  Collection<int, Task>  $habits
     */
    public static function attachStreaks(Collection $habits, Carbon $date): void
    {
        if ($habits->isEmpty()) {
            return;
        }

        $completedDays = TaskCompletion::query()
            ->whereIn('task_id', $habits->modelKeys())
            ->whereDate('date', '>=', $date->copy()->subDays(static::STREAK_WINDOW_DAYS)->toDateString())
            ->whereDate('date', '<=', $date->toDateString())
            ->get(['task_id', 'date'])
            ->groupBy('task_id')
            ->map(fn ($rows) => $rows->mapWithKeys(fn (TaskCompletion $row) => [$row->date->toDateString() => true])->all());

        $habits->each(fn (Task $habit) => $habit->streakCount = $habit->streak($date, $completedDays->get($habit->id, [])));
    }

    /**
     * Consecutive scheduled days completed, counting back from $date.
     * Only days the habit occurs on count (a Mon/Wed/Fri habit skips the
     * rest), and a habit not ticked yet today does not break the streak:
     * the day is not over.
     *
     * @param  array<string, true>  $completedDays  keyed by Y-m-d
     */
    public function streak(Carbon $date, array $completedDays): int
    {
        $day = $date->copy()->startOfDay();

        if (! isset($completedDays[$day->toDateString()])) {
            $day->subDay();
        }

        $streak = 0;

        for ($i = 0; $i < static::STREAK_WINDOW_DAYS; $i++, $day->subDay()) {
            if (! $this->occursOn($day)) {
                continue;
            }

            if (! isset($completedDays[$day->toDateString()])) {
                break;
            }

            $streak++;
        }

        return $streak;
    }

    // ---- Scopes -------------------------------------------------------

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', '!=', 'done');
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->open()->whereNotNull('due_at')->where('due_at', '<', now());
    }

    public function scopeDueOn(Builder $query, Carbon $date): Builder
    {
        return $query->open()->whereDate('due_at', $date->toDateString());
    }

    public function scopeRecurring(Builder $query): Builder
    {
        return $query->whereNotNull('repeat');
    }

    /**
     * List order from SPEC §8: by due date (so overdue comes first, undated
     * last), then importance high → low, then manual position.
     * Plain CASE expressions keep it portable between SQLite and MySQL.
     */
    public function scopeOrderedForList(Builder $query): Builder
    {
        return $query
            ->orderByRaw('CASE WHEN due_at IS NULL THEN 1 ELSE 0 END')
            ->orderBy('due_at')
            ->orderByRaw("CASE importance WHEN 'high' THEN 0 WHEN 'medium' THEN 1 ELSE 2 END")
            ->orderBy('position')
            ->orderBy('id');
    }
}
