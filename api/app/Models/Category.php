<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Facades\DB;

class Category extends Model
{
    protected $fillable = ['title', 'position'];

    /** A new category goes last in the sidebar. */
    protected static function booted(): void
    {
        static::creating(function (Category $category) {
            if (! $category->isDirty('position')) {
                $category->position = (int) static::max('position') + 1;
            }
        });
    }

    /**
     * Create a category ready to hold tasks: it gets a "General"
     * sub-category, since a task always belongs to a sub-category.
     */
    public static function createWithDefaultSubCategory(string $title): self
    {
        return DB::transaction(function () use ($title) {
            $category = static::create(['title' => $title]);
            $category->subCategories()->create(['title' => 'General']);

            return $category;
        });
    }

    /**
     * What deleting this category would also delete (cascade).
     *
     * @return array{sub_categories_count: int, tasks_count: int, open_tasks_count: int}
     */
    public function deletionImpact(): array
    {
        return [
            'sub_categories_count' => $this->subCategories()->count(),
            'tasks_count' => $this->tasks()->count(),
            'open_tasks_count' => $this->tasks()->where('tasks.status', '!=', 'done')->count(),
        ];
    }

    public function subCategories(): HasMany
    {
        return $this->hasMany(SubCategory::class)->orderBy('position');
    }

    public function tasks(): HasManyThrough
    {
        return $this->hasManyThrough(Task::class, SubCategory::class);
    }
}
