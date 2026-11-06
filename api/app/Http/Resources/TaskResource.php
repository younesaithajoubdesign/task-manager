<?php

namespace App\Http\Resources;

use App\Models\TimeEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Task
 */
class TaskResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * Expects `completions` and `timeEntries` to be eager loaded for today
     * only (see TaskController::index), so a list costs a fixed number of queries.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'notes' => $this->notes,
            'link' => $this->link,
            'type' => $this->type,
            'planned_minutes' => $this->planned_minutes,
            'importance' => $this->importance,
            'status' => $this->status,
            'due_at' => $this->due_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'repeat' => $this->repeat,
            'repeat_days' => $this->repeat_days,
            'position' => $this->position,
            'is_recurring' => $this->isRecurring(),
            'is_done_today' => $this->isRecurring()
                ? $this->completions->isNotEmpty()
                : $this->status === 'done',
            'tracked_seconds_today' => $this->whenLoaded(
                'timeEntries',
                fn () => (int) $this->timeEntries->sum(fn (TimeEntry $entry) => $entry->elapsedSeconds()),
            ),
            'sub_category_id' => $this->sub_category_id,
            'sub_category' => $this->whenLoaded('subCategory', fn () => [
                'id' => $this->subCategory->id,
                'title' => $this->subCategory->title,
                'color' => $this->subCategory->color,
                'category_id' => $this->subCategory->category_id,
            ]),
        ];
    }
}
