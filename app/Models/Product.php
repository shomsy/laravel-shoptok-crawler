<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;

/**
 * 🛍️ **Product Model**
 *
 * Represents a single product crawled from the Shoptok platform and stored locally.
 *
 * 🧠 Each product acts as a “snapshot” of a listing, containing:
 * - Its name, price, currency, brand, and image
 * - A relation to its category
 * - A stable `external_id` for deduplication across crawls
 *
 * **Purpose:**
 * - Centralized product storage and normalization
 * - Supports searching, filtering, and sorting for the frontend API
 * - Efficient updates using {@see \App\Services\ProductUpsertService}
 *
 * **Database Table:** `products`
 *
 * **Example Row:**
 * ```
 * | id | name              | price   | category_id |
 * |----|-------------------|---------|--------------|
 * |  1 | LG OLED55 CX 4K   | 1299.99 | 2            |
 * ```
 */
final class Product extends Model
{
    use HasFactory;

    /**
     * 🧱 Attributes that can be mass-assigned.
     *
     * Prevents mass-assignment vulnerabilities by explicitly whitelisting
     * the fields that can be set during creation or updates.
     *
     * @var string[]
     */
    protected $fillable = [
        'external_id',
        'name',
        'price',
        'currency',
        'image_url',
        'product_url',
        'category_id',
        'brand',
    ];

    /**
     * 💰 Attribute casting.
     *
     * Ensures numeric precision for the `price` field by automatically
     * converting it to a decimal with two digits after the point.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'price' => 'decimal:2',
    ];

    /**
     * 🎯 Dynamic query scope for filtering and sorting products.
     *
     * Handles:
     * - Filtering by category slug (via related Category model)
     * - Filtering by brand
     * - Full-text search in product names
     * - Sorting by price or date
     *
     * Example usage:
     * ```php
     * Product::filter(request())->paginate(20);
     * ```
     *
     * @param Builder $query   The query builder instance.
     * @param Request $filters The current HTTP request with query parameters.
     *
     * @return Builder  Modified query builder.
     */
    public function scopeFilter(Builder $query, Request $filters): Builder
    {
        return $query
            // 🔎 Filter by category slug (Recursive)
            ->when(
                value: $filters->filled(key: 'category'),
                callback: function (Builder $q) use ($filters) {
                    $slug = $filters->input(key: 'category');
                    $category = Category::where('slug', $slug)->first();

                    if ($category) {
                        $categoryIds = Category::getDescendantIds($category->id);
                        $q->whereIn('category_id', $categoryIds);
                    }
                }
            )

            // 🏷️ Filter by multiple brands (comma-separated)
            ->when(
                value: $filters->filled(key: 'brand'),
                callback: function (Builder $q) use ($filters) {
                    $brands = explode(',', $filters->input(key: 'brand'));
                    $q->whereIn('brand', $brands);
                }
            )

            // 🔍 Keyword search in product name
            ->when(
                value: $filters->filled(key: 'search'),
                callback: fn(Builder $q) => $q->where(column: 'name', operator: 'LIKE', value: '%' . $filters->input(key: 'search') . '%')
            )

            // ⚙️ Sorting logic
            ->tap(function (Builder $q) use ($filters) {
                $sort = $filters->input(key: 'sort');

                match ($sort) {
                    'price_asc'  => $q->orderBy(column: 'price', direction: 'asc'),
                    'price_desc' => $q->orderBy(column: 'price', direction: 'desc'),
                    default      => $q->latest(), // Fallback: sort by created_at DESC
                };
            });
    }

    /**
     * 🔗 Relationship: Product belongs to a single Category.
     *
     * Provides access to the parent category that this product belongs to.
     *
     * Example:
     * ```php
     * $product->category->name; // "Televizorji"
     * ```
     *
     * @return BelongsTo<Category, self>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(related: Category::class);
    }
}
