<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    /**
     * 🎯 Transforms a single Category model into a clean API response.
     *
     * Uses "whenLoaded" for children to prevent N+1 queries.
     *
     * @param Request $request
     *
     * @return array
     */
    public function toArray(Request $request) : array
    {
        return [
            'id'        => $this->id,
            'name'      => $this->name,
            'slug'      => $this->slug,
            'parent_id' => $this->parent_id,
            // Only include children if they were eager-loaded
            'children'  => $this->whenLoaded(relationship: 'children', value: function () {
                return self::collection(resource: $this->children);
            }),
        ];
    }
}
