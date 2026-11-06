<?php

namespace App\Http\Controllers;

use App\Http\Requests\StatsRequest;
use App\Services\StatsReport;
use Illuminate\Http\JsonResponse;

class StatsController extends Controller
{
    /**
     * Seconds tracked per day and per category, and a streak per habit,
     * for ?from=&to= (Y-m-d, both optional: the last 30 days by default).
     */
    public function __invoke(StatsRequest $request, StatsReport $report): JsonResponse
    {
        return response()->json([
            'data' => $report->build($request->from(), $request->to()),
        ]);
    }
}
