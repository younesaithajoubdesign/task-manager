<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class CategoryController extends Controller
{
    /**
     * The sidebar: categories in order, each with its sub-categories and
     * task counts (open, and all — the latter for delete warnings).
     */
    public function index(): AnonymousResourceCollection
    {
        return CategoryResource::collection(
            Category::query()->tap(fn ($query) => $this->withCounts($query))->orderBy('position')->orderBy('id')->get(),
        );
    }

    /**
     * Create a category with a "General" sub-category, last in the sidebar.
     */
    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $category = Category::createWithDefaultSubCategory($request->validated('title'));

        // present() re-reads the row, so the resource cannot infer 201 itself.
        return $this->present($category)->response()->setStatusCode(201);
    }

    public function update(UpdateCategoryRequest $request, Category $category): CategoryResource
    {
        $category->update($request->validated());

        return $this->present($category);
    }

    /**
     * Delete a category, its sub-categories and their tasks. When tasks
     * would go with it, answer 409 with the counts unless `force` is sent,
     * so nothing is lost by a stray click.
     */
    public function destroy(Request $request, Category $category): Response|JsonResponse
    {
        $impact = $category->deletionImpact();

        if ($impact['tasks_count'] > 0 && ! $request->boolean('force')) {
            return response()->json([
                'message' => "\"{$category->title}\" still holds {$impact['tasks_count']} task(s). Send force=1 to delete them too.",
                ...$impact,
            ], 409);
        }

        $category->delete();

        return response()->noContent();
    }

    private function present(Category $category): CategoryResource
    {
        return CategoryResource::make(
            Category::query()->whereKey($category->id)->tap(fn ($query) => $this->withCounts($query))->firstOrFail(),
        );
    }

    /**
     * @param  Builder<Category>  $query
     */
    private function withCounts(Builder $query): void
    {
        $open = fn ($q) => $q->where('tasks.status', '!=', 'done');

        $query
            ->with(['subCategories' => fn ($q) => $q->withCount(['tasks', 'tasks as open_tasks_count' => $open])])
            ->withCount(['tasks', 'tasks as open_tasks_count' => $open]);
    }
}
