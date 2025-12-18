<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use RuntimeException;

/**
 * 🧩 **Category Model**
 *
 * Represents a hierarchical product category in the local database.
 *
 * 🧠 Think of this as the *table of contents* for your crawler:
 * - Each category may have a **parent** (e.g. “TV Sprejemniki”)
 * - It can have **multiple children** (e.g. “Televizorji”, “TV Dodatki”)
 * - It holds **many products** via the {@see Product} relation
 *
 * **Why it exists:**
 * - Mirrors the Shoptok category structure for hierarchical browsing
 * - Enables sidebar generation, breadcrumbs, and recursive data crawling
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
    use HasFactory;

    /**
     * 🧱 Mass assignable attributes.
     *
     * Defines which fields can be safely bulk-filled during creation or updates,
     * such as during seeding or crawling.
     *
     * @var string[]
     */
    protected $fillable = [
        'name',
        'slug',
        'parent_id',
    ];

    /**
     * 🌳 Recursively finds all descendant IDs for a given category ID.
     *
     * This method walks the category tree using Eloquent relationships.
     * It performs breadth-first traversal (BFS) to avoid recursion limits.
     *
     * @return int[]
     */
    public static function getDescendantIds(int $categoryId) : array
    {
        $ids     = [$categoryId];
        $queue   = [$categoryId];
        $visited = [$categoryId => true];

        while ( ! empty($queue) ) {
            $parentId = array_shift($queue);

            // Use the Eloquent model instead of raw DB query
            $childrenIds = self::query()
                ->where(column: 'parent_id', operator: $parentId)
                ->pluck('id')
                ->all();

            foreach ($childrenIds as $childId) {
                if (! isset($visited[$childId])) {
                    $visited[$childId] = true;
                    $ids[]             = $childId;
                    $queue[]           = $childId;
                }
            }
        }

        return $ids;
    }

    /**
     * 🛡️ Bootstrap the model and its traits.
     */
    protected static function booted()
    {
        static::saving(static function (self $category) {
            // Prevent self-parenting
            if ($category->id && $category->parent_id === $category->id) {
                throw new RuntimeException("Circular parent_id detected for category: {$category->slug}");
            }
        });
    }

    /**
     * 🔗 Relationship: One category can contain multiple products.
     *
     * Provides access to all {@see Product} instances belonging to this category.
     *
     * Example:
     * ```php
     * // Get all products in this category
     * $products = $category->products;
     * ```
     *
     * @return HasMany<Product>
     */
    public function products() : HasMany
    {
        return $this->hasMany(related: Product::class);
    }

    /**
     * 🔗 Relationship: Category may belong to a parent category.
     *
     * Allows navigation up the hierarchy (used for breadcrumb generation).
     *
     * Example:
     * ```php
     * $subcategory->parent->name; // "TV Sprejemniki"
     * ```
     *
     * @return BelongsTo<Category, self>
     */
    public function parent() : BelongsTo
    {
        return $this->belongsTo(related: self::class, foreignKey: 'parent_id');
    }

    /**
     * 🔗 Relationship: Category can have multiple child categories.
     *
     * Enables recursive traversal or display of nested category trees.
     *
     * Example:
     * ```php
     * $root->children->pluck('name');
     * // ["Televizorji", "TV Dodatki"]
     * ```
     *
     * @return HasMany<Category>
     */
    public function children() : HasMany
    {
        return $this->hasMany(related: self::class, foreignKey: 'parent_id');
    }

    /**
     * 🎯 Query Scope — filters only root categories (no parent).
     *
     * A helper scope for quickly retrieving all top-level categories.
     *
     * Example:
     * ```php
     * $roots = Category::roots()->get();
     * ```
     *
     * @param Builder $query
     *
     * @return Builder
     */
    public function scopeRoots(Builder $query) : Builder
    {
        return $query->whereNull('parent_id');
    }
}
