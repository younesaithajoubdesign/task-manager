<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubCategory extends Model
{
    protected $fillable = ['category_id', 'title', 'color', 'position'];

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
