<?php

namespace App\Http\Resources;

use App\Models\SubCategory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SubCategory
 */
class SubCategoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'category_id' => $this->category_id,
            'title' => $this->title,
            'color' => $this->color,
            'position' => $this->position,
            'open_tasks_count' => $this->whenCounted('open_tasks_count'),
        ];
    }
}
