<?php

namespace App\Http\Controllers;

use App\Http\Requests\MoneyPeriodRequest;
use App\Http\Requests\StoreMoneyEntryRequest;
use App\Http\Requests\UpdateMoneyEntryRequest;
use App\Http\Resources\MoneyEntryResource;
use App\Models\MoneyEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * Incomes and expenses, typed in as they happen.
 */
class MoneyEntryController extends Controller
{
    /** How many past categories the entry form suggests. */
    public const SUGGESTED_CATEGORIES = 30;

    /**
     * Entries of ?from=&to= (the last 30 days by default), newest first, with
     * the categories used so far (most used first) for the form's suggestions.
     */
    public function index(MoneyPeriodRequest $request): AnonymousResourceCollection
    {
        $entries = MoneyEntry::query()
            ->between($request->from(), $request->to())
            ->latestFirst()
            ->get();

        $categories = MoneyEntry::query()
            ->whereNotNull('category')
            ->selectRaw('category, count(*) as uses')
            ->groupBy('category')
            ->orderByDesc('uses')
            ->orderBy('category')
            ->limit(self::SUGGESTED_CATEGORIES)
            ->pluck('category');

        return MoneyEntryResource::collection($entries)->additional([
            'meta' => [
                'from' => $request->from()->toDateString(),
                'to' => $request->to()->toDateString(),
                'categories' => $categories,
            ],
        ]);
    }

    public function store(StoreMoneyEntryRequest $request): JsonResponse
    {
        $entry = MoneyEntry::create($request->entryAttributes());

        return MoneyEntryResource::make($entry)->response()->setStatusCode(201);
    }

    public function update(UpdateMoneyEntryRequest $request, MoneyEntry $moneyEntry): MoneyEntryResource
    {
        $moneyEntry->update($request->entryAttributes());

        return MoneyEntryResource::make($moneyEntry);
    }

    public function destroy(MoneyEntry $moneyEntry): Response
    {
        $moneyEntry->delete();

        return response()->noContent();
    }
}
