<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCounterResetRequest;
use App\Models\CounterReset;
use App\Services\CounterSummary;
use Illuminate\Http\JsonResponse;

/**
 * The days-without counter: one unnamed bad habit, the days since it last
 * happened, and when each restart was.
 */
class CounterController extends Controller
{
    public function show(CounterSummary $summary): JsonResponse
    {
        return response()->json(['data' => $summary->build()]);
    }

    /**
     * Start counting, or restart from zero: it happened (now, or at `reset_at`).
     */
    public function reset(StoreCounterResetRequest $request, CounterSummary $summary): JsonResponse
    {
        CounterReset::create(['reset_at' => $request->resetAt()]);

        return response()->json(['data' => $summary->build()], 201);
    }

    /**
     * Remove a reset logged by mistake: the run it ended joins the next one.
     */
    public function destroyReset(CounterReset $counterReset, CounterSummary $summary): JsonResponse
    {
        $counterReset->delete();

        return response()->json(['data' => $summary->build()]);
    }
}
