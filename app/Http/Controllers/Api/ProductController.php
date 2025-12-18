<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\ProductCollection;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * 🛒 ProductController
 *
 * Provides the main API endpoint for listing products.
 *
 * Features:
 * - Full-text search and brand filtering
 * - Sorting (price ascending/descending, latest)
 * - Dynamic brand aggregation
 * - Sidebar and breadcrumb generation
 * - Aggressive caching for fast response times
 */
final class ProductController extends Controller
{
    /**
     * Get a paginated list of products with optional filters.
     *
     * Handles:
     * - `?search=` → search by product name
     * - `?brand=` → filter by brand
     * - `?sort=price_asc|price_desc` → sorting
     * - Cached sidebar tree and available brands
     *
     * Example:
     * ```
     * GET /api/products?search=lg&brand=Samsung&sort=price_asc
     * ```
     */
    public function index(Request $request)
    {
        // Include model timestamps in cache key to auto-refresh when data changes
        $lastUpdate = Product::max('updated_at') ?? now();
        $cacheKey   = $this->makeCacheKey(request: $request, lastUpdate: $lastUpdate);

        return Cache::remember(key: $cacheKey, ttl: now()->addMinutes(30), callback: function () use ($request) {
            // Build product query
            $query = Product::with('category')->filter($request);

            // Aggregate available brands (short cache)
            $availableBrands = Cache::remember(
                key     : 'brands:' . md5(string: $request->fullUrl()),
                ttl     : now()->addSeconds(30),
                callback: fn () => $this->getAvailableBrandsOptimized(query: $query)
            );

            // Paginate results
            $products = $query->paginate(20)->withQueryString();

            // Sidebar tree (cached structure)
            $sidebarTree = $this->buildSidebarTree();

            // Breadcrumbs
            $breadcrumbs = $this->buildBreadcrumbs(request: $request);

            // ✅ Response payload
            return response()->json(data: [
                                              'category'         => null,
                                              'breadcrumbs'      => $breadcrumbs,
                                              'sidebar_tree'     => CategoryResource::collection(resource: $sidebarTree),
                                              'available_brands' => $availableBrands,
                                              'products'         => new ProductCollection(resource: $products),
                                          ]);
        });
    }

    /**
     * Build a unique cache key per query.
     *
     * Ensures freshness when product data changes.
     */
    private function makeCacheKey(Request $request, $lastUpdate) : string
    {
        return sprintf(
            'products:v2:%s:%s',
            md5(string: $request->fullUrl()),
            md5(string: $lastUpdate)
        );
    }

    /**
     * Optimized brand aggregation using Query Builder.
     *
     * Avoids Eloquent overhead — performs a DISTINCT scan
     * on indexed columns for faster performance.
     */
    private function getAvailableBrandsOptimized($query)
    {
        $baseSql = $query->toBase();
        $table   = (new Product)->getTable();

        return DB::table(table: $table)
            ->select(columns: 'brand')
            ->whereNotNull(columns: 'brand')
            ->when(value: $baseSql->wheres, callback: function ($q) use ($baseSql) {
                foreach ($baseSql->wheres as $where) {
                    if (isset($where['column'], $where['value'], $where['operator'])) {
                        $q->where(column: $where['column'], operator: $where['operator'], value: $where['value']);
                    }
                }
            })
            ->distinct()
            ->orderBy(column: 'brand')
            ->pluck(column: 'brand');
    }

    /**
     * Build sidebar category tree (root + children).
     *
     * Always prioritizes “TV sprejemniki” as the top-level node.
     * Falls back to the first available root category if missing.
     */
    private function buildSidebarTree()
    {
        return Cache::remember(key: 'sidebar_tree:v2', ttl: now()->addMinutes(30), callback: function () {
            $root = Category::where(column: 'slug', operator: 'tv-sprejemniki')->first()
                ?? Category::whereNull('parent_id')->first()
                ?? Category::orderBy('id')->first();

            if (! $root) {
                return collect();
            }

            // Load children or fallback to all remaining categories
            $children = Category::where(column: 'parent_id', operator: $root->id)
                ->orderBy('name')
                ->get();

            if ($children->isEmpty()) {
                $children = Category::where(column: 'id', operator: '!=', value: $root->id)
                    ->orderBy('name')
                    ->get();
            }

            $root->setRelation(relation: 'children', value: $children);

            return collect(value: [$root]);
        });
    }

    /**
     * Build breadcrumb navigation.
     *
     * Always starts with a “Search Results” item,
     * followed by the active search query (if any).
     */
    private function buildBreadcrumbs(Request $request) : array
    {
        $crumbs = [
            ['name' => 'Search Results', 'slug' => 'search', 'url' => '/products'],
        ];

        if ($request->filled(key: 'search')) {
            $crumbs[] = [
                'name' => '"' . e(value: $request->search) . '"',
                'slug' => 'query',
                'url'  => '#',
            ];
        }

        return $crumbs;
    }
}
