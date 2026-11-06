<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TimeEntry extends Model
{
    protected $fillable = ['task_id', 'started_at', 'ended_at', 'duration_seconds'];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at'   => 'datetime',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function isRunning(): bool
    {
        return $this->ended_at === null;
    }

    /**
     * Seconds elapsed. For a running entry this is computed from started_at,
     * so the value survives a page refresh or a browser restart.
     */
    public function elapsedSeconds(): int
    {
        return $this->isRunning()
            ? $this->started_at->diffInSeconds(now())
            : (int) $this->duration_seconds;
    }

    /** Close the entry and freeze its duration. */
    public function stop(): void
    {
        $this->ended_at = now();
        $this->duration_seconds = $this->started_at->diffInSeconds($this->ended_at);
        $this->save();
    }
}
