<?php

namespace App\Http\Resources;

use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Timer state of one task. The client ticks its display from
 * `tracked_seconds_today` and re-syncs from this payload on load (SPEC §3 rule 1).
 *
 * Expects Task::listRelations() and runningEntry to be loaded.
 *
 * @mixin Task
 */
class TimerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $runningEntry = $this->runningEntry;

        return [
            'task' => TaskResource::make($this->resource),
            'is_running' => $runningEntry !== null,
            'started_at' => $runningEntry?->started_at->toIso8601String(),
            'current_entry_seconds' => $runningEntry?->elapsedSeconds() ?? 0,
            'tracked_seconds_today' => (int) $this->timeEntries->sum(fn ($entry) => $entry->elapsedSeconds()),
            'planned_seconds' => $this->planned_minutes ? $this->planned_minutes * 60 : null,
        ];
    }
}
