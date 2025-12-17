<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    /**
     * 🎯 Transforms a single Product model into a clean API response.
     *
     * Uses "whenLoaded" to prevent N+1 queries and extra DB hits.
     *
     * @param Request $request
     * @return array
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'external_id' => $this->external_id,
            'name' => $this->name,
            'brand' => $this->brand ?? null,
            'price' => (float) $this->price,
            'currency' => $this->currency ?? 'EUR',
            'image_url' => $this->image_url,
            'product_url' => $this->product_url,
            // Only include category if it was eager-loaded
            'category' => $this->whenLoaded('category', fn() => [
                'id' => $this->category->id,
                'name' => $this->category->name,
                'slug' => $this->category->slug,
            ]),
        ];
    }
}
