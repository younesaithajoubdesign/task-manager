<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
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
