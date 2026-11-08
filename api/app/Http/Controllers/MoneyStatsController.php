<?php

namespace App\Http\Controllers;

use App\Http\Requests\MoneyPeriodRequest;
use App\Services\MoneyReport;
use Illuminate\Http\JsonResponse;

class MoneyStatsController extends Controller
{
    /**
     * Balance, pace, averages and breakdowns for ?from=&to=
     * (the last 30 days by default).
     */
    public function __invoke(MoneyPeriodRequest $request, MoneyReport $report): JsonResponse
    {
        return response()->json([
            'data' => $report->build($request->from(), $request->to()),
        ]);
    }
}
