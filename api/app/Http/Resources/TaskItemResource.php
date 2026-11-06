<?php

namespace App\Http\Resources;

use App\Models\TaskItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TaskItem
 */
class TaskItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'task_id' => $this->task_id,
            'title' => $this->title,
            'link' => $this->link,
            'position' => $this->position,
            'is_done' => $this->isDone(),
            'done_at' => $this->done_at?->toIso8601String(),
        ];
    }
}
