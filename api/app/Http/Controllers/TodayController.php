<?php

namespace App\Http\Controllers;

use App\Http\Resources\TaskResource;
use App\Models\Task;
use Illuminate\Http\JsonResponse;

class TodayController extends Controller
{
    /**
     * The opening screen in one call: overdue, due today, and today's habits.
     */
    public function __invoke(): JsonResponse
    {
        $groups = Task::today();

        return response()->json([
            'data' => [
                'date' => today()->toDateString(),
                'overdue' => TaskResource::collection($groups['overdue']),
                'due_today' => TaskResource::collection($groups['due_today']),
                'habits' => TaskResource::collection($groups['habits']),
            ],
        ]);
    }
}
