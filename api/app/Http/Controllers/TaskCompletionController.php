<?php

namespace App\Http\Controllers;

use App\Http\Resources\TaskResource;
use App\Models\Task;
use App\Services\TimerService;

class TaskCompletionController extends Controller
{
    public function __construct(private TimerService $timer) {}

    /**
     * Complete a task (closes its running timer first).
     */
    public function store(Task $task): TaskResource
    {
        $this->timer->complete($task);

        return TaskResource::make($task->refresh()->load(Task::listRelations()));
    }

    /**
     * Undo today's completion.
     */
    public function destroy(Task $task): TaskResource
    {
        $this->timer->uncomplete($task);

        return TaskResource::make($task->refresh()->load(Task::listRelations()));
    }
}
