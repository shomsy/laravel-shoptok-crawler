<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 🧩 **Category Model**
 *
 * Represents a single product category in your local database.
 *
 * 🧠 Think of this as the “table of contents” for your crawler:
 * - It can have **a parent category** (like “TV Sprejemniki”).
 * - It can have **many child categories** (like “Televizorji”, “TV dodatki”).
 * - It can hold **many products** (via {@see Product} relation).
 *
 * **Why it exists:**
 * - Shoptok’s category structure is hierarchical — this model mirrors that.
 * - Makes filtering, sidebar generation, and recursive crawling possible.
 *
 * **Database Table:** `categories`
 *
 * **Example structure:**
 * ```
 * TV Sprejemniki
 * ├── Televizorji
 * └── TV Dodatki
 * ```
 */
final class Category extends Model
{
    /**
     * 🧱 The attributes that can be mass-assigned (for seeding and upserting).
     *
     * @var string[]
     */
    protected $fillable = ['name', 'slug', 'parent_id'];

    /**
     * 🔗 Relationship: one category can have many products.
     *
     * @return HasMany<Product>
     *
     * Example:
     * ```
     * // Collection of all products in this category
     * $category->products;
     * ```
     */
    public function products(): HasMany
    {
        return $this->hasMany(related: Product::class);
    }

    /**
     * 🔗 Relationship: this category may belong to a parent category.
     *
     * @return BelongsTo<Category, self>
     *
     * Example:
     * ```
     * // "TV Sprejemniki"
     * $subcategory->parent->name;
     * ```
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(related: self::class, foreignKey: 'parent_id');
    }

    /**
     * 🔗 Relationship: this category can have multiple child categories.
     *
     * @return HasMany<Category>
     *
     * Example:
     *
     * ```
     * // [Televizorji, TV Dodatki]
     * $root->children;
     * ```
     */
    public function children(): HasMany
    {
        return $this->hasMany(related: self::class, foreignKey: 'parent_id');
    }

    /**
     * 🎯 Query scope: fetch only “root” categories (no parent).
     *
     * Example:
     * ```
     * Category::roots()->get();
     * ```
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeRoots(Builder $query): Builder
    {
        return $query->whereNull(columns: 'parent_id');
    }
}
