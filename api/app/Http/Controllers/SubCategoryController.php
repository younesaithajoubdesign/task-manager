<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSubCategoryRequest;
use App\Http\Requests\UpdateSubCategoryRequest;
use App\Http\Resources\SubCategoryResource;
use App\Models\SubCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SubCategoryController extends Controller
{
    /**
     * Create a sub-category, last in its category; colour from the palette if none is sent.
     */
    public function store(StoreSubCategoryRequest $request): SubCategoryResource
    {
        return $this->present(SubCategory::create($request->validated()));
    }

    public function update(UpdateSubCategoryRequest $request, SubCategory $subCategory): SubCategoryResource
    {
        $subCategory->update($request->validated());

        return $this->present($subCategory);
    }

    /**
     * Delete a sub-category and its tasks; 409 with counts unless `force`.
     */
    public function destroy(Request $request, SubCategory $subCategory): Response|JsonResponse
    {
        $impact = $subCategory->deletionImpact();

        if ($impact['tasks_count'] > 0 && ! $request->boolean('force')) {
            return response()->json([
                'message' => "\"{$subCategory->title}\" still holds {$impact['tasks_count']} task(s). Send force=1 to delete them too.",
                ...$impact,
            ], 409);
        }

        $subCategory->delete();

        return response()->noContent();
    }

    private function present(SubCategory $subCategory): SubCategoryResource
    {
        return SubCategoryResource::make(
            $subCategory->loadCount(['tasks', 'tasks as open_tasks_count' => fn ($q) => $q->open()]),
        );
    }
}
