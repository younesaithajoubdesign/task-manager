<?php

namespace App\Http\Controllers;

use App\Http\Requests\IndexTaskRequest;
use App\Http\Resources\TaskResource;
use App\Models\Task;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TaskController extends Controller
{
    /**
     * List tasks. With no `status` filter only open tasks are returned
     * (the "All" button); `status=done` reaches completed one-off tasks.
     */
    public function index(IndexTaskRequest $request): AnonymousResourceCollection
    {
        $today = today()->toDateString();

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
            ->with([
                'subCategory',
                'completions' => fn ($query) => $query->whereDate('date', $today),
                'timeEntries' => fn ($query) => $query->whereDate('started_at', $today),
            ])
            ->orderedForList()
            ->get();

        return TaskResource::collection($tasks);
    }
}
