<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 🛍️ **Product Model**
 *
 * Represents a single product that has been crawled from Shoptok and stored locally.
 *
 * 🧠 Think of this as a “snapshot” of a product listing:
 * - It belongs to a category (like “Televizorji”).
 * - It contains essential info: name, price, currency, image, and link.
 * - It can be re-crawled and updated without creating duplicates.
 *
 * **Why it exists:**
 * - Stores parsed data from the crawler in a normalized structure.
 * - Enables fast filtering, pagination, and display on the frontend.
 * - The `external_id` keeps data consistent even after multiple crawls.
 *
 * **Database Table:** `products`
 *
 * **Example:**
 * ```
 * | id | name          | price  | category_id |
 * |----|----------------|--------|--------------|
 * | 1  | LG OLED55...   | 1299.99| 2            |
 * ```
 */
final class Product extends Model
{
    /**
     * 🧱 The attributes that are mass assignable.
     *
     * This allows safe bulk creation or updates through {@see \App\Services\ProductUpsertService}.
     *
     * @var string[]
     */
    protected $fillable
        = [
            'external_id',
            'name',
            'price',
            'currency',
            'image_url',
            'product_url',
            'category_id',
        ];

    /**
     * 💰 Casts numeric fields to proper types.
     *
     * Here, we ensure that `price` is always treated as a decimal
     * with exactly two digits after the decimal point.
     *
     * @var array<string, string>
     */
    protected $casts
        = [
            'price' => 'decimal:2',
        ];

    /**
     * 🔗 Relationship: this product belongs to a single category.
     *
     * Example:
     * ```
     * $product->category->name; // "Televizorji"
     * ```
     *
     * @return BelongsTo<Category, self>
     */
    public function category() : BelongsTo
    {
        return $this->belongsTo(related: Category::class);
    }
}
