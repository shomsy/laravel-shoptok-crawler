<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryCollection;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\ProductCollection;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * 🧭 **CategoryController**
 *
 * Handles all API endpoints related to category browsing and product listing.
 *
 * Responsibilities:
 * - Return root categories for the sidebar or homepage.
 * - Display a specific category and its associated products.
 * - Provide related data such as breadcrumbs and available brands.
 *
 * Features:
 * - Caching via Redis for performance.
 * - Recursive category traversal using an iterative BFS.
 * - Consistent resource formatting using Laravel Resource classes.
 */
final class CategoryController extends Controller
{
    /**
     * 📂 Fetch all **root categories** (top-level entries without a parent).
     *
     * Purpose:
     * - Provides the base navigation structure for the frontend (e.g. sidebar or header).
     * - Cached in Redis for 1 hour for instant load times.
     * - Uses a lightweight Query Builder for maximum performance.
     *
     * Example:
     * ```
     * GET /api/categories
     * ```
     *
     * Response structure:
     * ```
     *
     * {
     *   "data": [
     *     { "id": 1, "name": "TV sprejemniki", "slug": "tv-sprejemniki", "parent_id": null },
     *
     *     { "id": 2, "name": "Audio naprave", "slug": "audio-naprave", "parent_id": null }
     *   ]
     * }
     *
     * ```
     *
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function index(Request $request)
    {
        return Cache::remember(key: 'categories:roots', ttl: now()->addHour(), callback: function () {
            // 🚀 Fetch all root categories (no parent_id)
            $roots = DB::table(table: 'categories')
                ->select(columns: ['id', 'name', 'slug', 'parent_id'])
                ->whereNull(columns: 'parent_id')
                ->orderBy(column: 'name')
                ->get();

            return response()->json(data: ['data' => $roots]);
        });
    }

    /**
     * 📦 Display a single category along with its products and metadata.
     *
     * Returns:
     * - Category details.
     * - Paginated product list (with filters and sorting).
     * - Available brands in the category.
     * - Sidebar hierarchy.
     * - Breadcrumb trail.
     *
     * @param Request $request
     * @param string  $slug
     *
     * @return JsonResponse
     */
    public function show(Request $request, string $slug)
    {
        $cacheKey = "category_view:v6:{$slug}:" . md5(string: $request->fullUrl());

        return Cache::remember(key: $cacheKey, ttl: now()->addMinutes(30), callback: function () use ($slug, $request) {
            /** @var Category $category */
            $category = Category::with('parent')
                ->where(column: 'slug', operator: $slug)
                ->firstOrFail();

            // 🌳 Sidebar structure (virtual hierarchy)
            $sidebarTree = $this->buildSidebarTree();

            // 🔍 Determine which category IDs to include (recursively)
            $categoryIds = $this->resolveCategoryScope(category: $category);

            // 🏗️ Base query for products within this category scope
            $productsQuery = Product::query()
                ->whereIn('category_id', $categoryIds)
                ->with(relations: 'category');

            // 🏷️ Filter by one or more brands
            if ($request->filled(key: 'brand')) {
                $brands = explode(separator: ',', string: $request->input(key: 'brand'));
                $productsQuery->whereIn('brand', $brands);
            }

            // ⚙️ Sorting options
            match ($request->input(key: 'sort')) {
                'price_asc'  => $productsQuery->orderBy('price', 'asc'),
                'price_desc' => $productsQuery->orderBy('price', 'desc'),
                default      => $productsQuery->latest(),
            };

            // 📄 Pagination (20 per page)
            $products = $productsQuery->paginate(20)->withQueryString();

            // 🏷️ Retrieve available brands (distinct + ordered)
            $availableBrands = DB::table(table: 'products')
                ->select(columns: 'brand')
                ->whereIn(column: 'category_id', values: $categoryIds)
                ->whereNotNull(columns: 'brand')
                ->distinct()
                ->orderBy(column: 'brand')
                ->pluck(column: 'brand');

            // 🧭 Build breadcrumb navigation
            $breadcrumbs = $this->buildBreadcrumbs(category: $category);

            // ✅ Structured API response
            return response()->json(data: [
                                              'category'         => new CategoryResource(resource: $category),
                                              'breadcrumbs'      => $breadcrumbs,
                                              'sidebar_tree'     => new CategoryCollection(resource: $sidebarTree),
                                              'available_brands' => $availableBrands,
                                              'products'         => new ProductCollection(resource: $products),
                                          ]);
        });
    }

    /**
     * 🌲 Builds a **virtual sidebar hierarchy** for the frontend.
     *
     * Rules:
     * - Always returns exactly one top-level category ("TV Sprejemniki").
     * - All other categories appear as its children, regardless of actual DB hierarchy.
     * - Ensures that the sidebar always has at least one root node.
     *
     * @return Collection<Category>
     */
    private function buildSidebarTree()
    {
        // 🌳 Always prefer the known root “TV sprejemniki”
        $root = Category::where('slug', 'tv-sprejemniki')->first()
            ?? Category::whereNull('parent_id')->first()
            ?? Category::orderBy('id')->first();

        // If there are no categories at all, return an empty collection
        if (! $root) {
            return collect();
        }

        // All other categories become "virtual" children of the root
        $children = Category::where(column: 'id', operator: '!=', value: $root->id)
            ->orderBy('name')
            ->get();

        $root->setRelation(relation: 'children', value: $children);

        // Return a single-item collection for serialization
        return collect(value: [$root]);
    }

    /**
     * 🔍 Resolves all category IDs to be included when fetching products.
     *
     * - Includes the current category itself.
     * - Includes all descendants recursively (children, grandchildren, etc.).
     * - Uses iterative BFS traversal for safe recursion depth.
     *
     * @param Category $category
     *
     * @return array<int>
     */
    private function resolveCategoryScope(Category $category) : array
    {
        return $this->getDescendantIds(categoryId: $category->id);
    }

    /**
     * 🌿 Recursively fetch all descendant category IDs using BFS traversal.
     *
     * @param int $categoryId
     *
     * @return array<int>
     */
    private function getDescendantIds(int $categoryId) : array
    {
        $ids     = [$categoryId];
        $queue   = [$categoryId];
        $visited = [$categoryId => true];

        while ( ! empty($queue) ) {
            $parentId = array_shift(array: $queue);

            $childrenIds = DB::table(table: 'categories')
                ->where(column: 'parent_id', operator: $parentId)
                ->pluck(column: 'id')
                ->toArray();

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
     * 🧭 Builds a **breadcrumb trail** from the current category up to the root.
     *
     * Features:
     * - Prevents infinite loops (cycle detection via visited slugs).
     * - Enforces a maximum depth of 10 levels.
     * - Ensures the root “TV Sprejemniki” always appears at the top.
     *
     * @param Category $category
     *
     * @return array<int, array{name: string, slug: string, url: string}>
     */
    private function buildBreadcrumbs(Category $category) : array
    {
        $breadcrumbs  = collect();
        $visitedSlugs = [];

        // 🌀 Traverse upwards through parent relationships
        while (
            $category &&
            ! in_array(needle: $category->slug, haystack: $visitedSlugs, strict: true) &&
            $breadcrumbs->count() < 10
        ) {
            $visitedSlugs[] = $category->slug;

            $breadcrumbs->prepend(value: [
                                             'name' => $category->name,
                                             'slug' => $category->slug,
                                             'url'  => "/category/{$category->slug}",
                                         ]);

            // Use loaded parent if available, otherwise query directly
            $category = $category->relationLoaded(key: 'parent')
                ? $category->parent
                : $category->parent()->first();
        }

        // 🌟 Force “TV Sprejemniki” to be the first breadcrumb if missing
        $rootSlug = 'tv-sprejemniki';
        if ($breadcrumbs->isNotEmpty() && $breadcrumbs->first()['slug'] !== $rootSlug) {
            $root = Category::where(column: 'slug', operator: $rootSlug)->first();
            if ($root) {
                $breadcrumbs->prepend(value: [
                                                 'name' => $root->name,
                                                 'slug' => $root->slug,
                                                 'url'  => "/category/{$root->slug}",
                                             ]);
            }
        }

        return $breadcrumbs->values()->all();
    }
}
