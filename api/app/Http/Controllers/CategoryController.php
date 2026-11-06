<?php

namespace App\Http\Controllers;

use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CategoryController extends Controller
{
    /**
     * The sidebar: categories in order, each with its sub-categories and
     * open-task counts. Create / update / delete arrive with SPEC §7 step 8.
     */
    public function index(): AnonymousResourceCollection
    {
        $openTasks = fn ($query) => $query->where('tasks.status', '!=', 'done');

        $categories = Category::query()
            ->with(['subCategories' => fn ($query) => $query->withCount(['tasks as open_tasks_count' => $openTasks])])
            ->withCount(['tasks as open_tasks_count' => $openTasks])
            ->orderBy('position')
            ->orderBy('id')
            ->get();

        return CategoryResource::collection($categories);
    }
}
