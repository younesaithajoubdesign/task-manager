<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubCategory extends Model
{
    protected $fillable = ['category_id', 'title', 'color', 'position'];

    /** Badge colours offered by default, the same as the seeder's. */
    public const PALETTE = ['#ec4899', '#eab308', '#22c55e', '#06b6d4', '#f97316', '#3b82f6', '#8b5cf6'];

    /**
     * A new sub-category goes last in its category and, without a colour,
     * takes the next palette colour so neighbours stay distinguishable.
     */
    protected static function booted(): void
    {
        static::creating(function (SubCategory $sub) {
            if (! $sub->isDirty('position')) {
                $sub->position = (int) static::where('category_id', $sub->category_id)->max('position') + 1;
            }

            if (! $sub->color) {
                $sub->color = static::PALETTE[static::count() % count(static::PALETTE)];
            }
        });
    }

    /**
     * What deleting this sub-category would also delete.
     *
     * @return array{tasks_count: int, open_tasks_count: int}
     */
    public function deletionImpact(): array
    {
        return [
            'tasks_count' => $this->tasks()->count(),
            'open_tasks_count' => $this->tasks()->open()->count(),
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class)->orderBy('position');
    }

    /**
     * Where quick capture puts a task when no sub-category is given:
     * the one used by the most recently created task (you keep capturing
     * into the same place), else the first one in sidebar order.
     */
    public static function forQuickCapture(): ?self
    {
        $lastUsedId = Task::query()->latest('id')->value('sub_category_id');

        if ($lastUsedId !== null) {
            return static::find($lastUsedId);
        }

        return static::query()
            ->join('categories', 'categories.id', '=', 'sub_categories.category_id')
            ->orderBy('categories.position')
            ->orderBy('sub_categories.position')
            ->select('sub_categories.*')
            ->first();
    }
}
