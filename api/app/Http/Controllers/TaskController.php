<?php

namespace App\Http\Controllers;

use App\Http\Requests\IndexTaskRequest;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Http\Resources\TaskResource;
use App\Models\Task;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class TaskController extends Controller
{
    /**
     * List tasks. With no `status` filter only open tasks are returned
     * (the "All" button); `status=done` reaches completed one-off tasks.
     */
    public function index(IndexTaskRequest $request): AnonymousResourceCollection
    {
        $tasks = Task::query()
            ->when(
                $request->filled('status'),
                fn ($query) => $query->where('status', $request->validated('status')),
                fn ($query) => $query->open(),
            )
            ->when(
                $request->filled('sub_category_id'),
                fn ($query) => $query->where('sub_category_id', $request->validated('sub_category_id')),
            )
            ->when(
                $request->filled('category_id'),
                fn ($query) => $query->whereRelation('subCategory', 'category_id', $request->validated('category_id')),
            )
            ->with(Task::listRelations())
            ->orderedForList()
            ->get();

        return TaskResource::collection($tasks);
    }

    /**
     * Create a task. Quick capture sends only a title (201).
     */
    public function store(StoreTaskRequest $request): TaskResource
    {
        $task = Task::create($request->taskAttributes());

        return TaskResource::make($task->load(Task::listRelations()));
    }

    /**
     * Partially update a task: only the fields sent change.
     */
    public function update(UpdateTaskRequest $request, Task $task): TaskResource
    {
        $task->update($request->taskAttributes());

        return TaskResource::make($task->load(Task::listRelations()));
    }

    /**
     * Delete a task with its completions and time entries (cascade).
     */
    public function destroy(Task $task): Response
    {
        $task->delete();

        return response()->noContent();
    }
}
