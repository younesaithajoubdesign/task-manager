<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of a task's checklist (a film, an article, a subject), with an
 * optional link to the resource itself.
 */
class TaskItem extends Model
{
    protected $fillable = ['task_id', 'title', 'link', 'position', 'done_at'];

    protected $casts = [
        'done_at' => 'datetime',
    ];

    /** A new item goes at the end of its task's list. */
    protected static function booted(): void
    {
        static::creating(function (TaskItem $item) {
            if (! $item->isDirty('position')) {
                $item->position = (int) static::where('task_id', $item->task_id)->max('position') + 1;
            }
        });
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function isDone(): bool
    {
        return $this->done_at !== null;
    }

    /** Tick or untick. Ticking twice keeps the first date. */
    public function markDone(bool $done): void
    {
        $this->done_at = $done ? ($this->done_at ?? now()) : null;
    }

    /**
     * To-do items first in list order, then done ones, most recent first.
     */
    public function scopeChecklistOrder(Builder $query): Builder
    {
        return $query
            ->orderByRaw('done_at IS NULL DESC')
            ->orderByDesc('done_at')
            ->orderBy('position')
            ->orderBy('id');
    }
}
