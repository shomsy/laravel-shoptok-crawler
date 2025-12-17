<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class CategoryCollection extends ResourceCollection
{
    /**
     * 📦 Wraps a list of categories into a structured JSON response.
     *
     * This class ensures consistent structure for:
     * - Sidebar navigation
     * - Category index endpoints
     * - Any nested children listings
     *
     * Each category is transformed using CategoryResource.
     */
    public function toArray(Request $request): array
    {
        return [
            'data' => CategoryResource::collection($this->collection),
            'meta' => [
                'total' => $this->collection->count(),
                'has_children' => $this->
                                    collection->
                                    contains(static fn ($c) => $c->relationLoaded('children') && $c->children->isNotEmpty()),
            ],
        ];
    }
}
