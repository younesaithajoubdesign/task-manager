<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTaskItemRequest;
use App\Http\Requests\UpdateTaskItemRequest;
use App\Http\Resources\TaskItemResource;
use App\Models\Task;
use App\Models\TaskItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * A task's checklist: what to watch, read or write on, each with an
 * optional link, at hand when the timer starts.
 */
class TaskItemController extends Controller
{
    /**
     * To-do items in list order, then done ones, most recent first.
     */
    public function index(Task $task): AnonymousResourceCollection
    {
        return TaskItemResource::collection($task->items()->checklistOrder()->get());
    }

    /**
     * Add an item at the end of the list.
     */
    public function store(StoreTaskItemRequest $request, Task $task): JsonResponse
    {
        $item = $task->items()->create($request->validated());

        return TaskItemResource::make($item)->response()->setStatusCode(201);
    }

    /**
     * Rename, change the link, or tick / untick (`done`).
     */
    public function update(UpdateTaskItemRequest $request, TaskItem $taskItem): TaskItemResource
    {
        $taskItem->fill($request->safe()->except('done'));

        if ($request->has('done')) {
            $taskItem->markDone($request->boolean('done'));
        }

        $taskItem->save();

        return TaskItemResource::make($taskItem);
    }

    public function destroy(TaskItem $taskItem): Response
    {
        $taskItem->delete();

        return response()->noContent();
    }
}
