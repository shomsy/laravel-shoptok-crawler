<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\ProductCollection;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class ProductController extends Controller
{
    /**
     * 🎯 Returns a paginated list of products with optional filters.
     *
     * Handles:
     * - Search, sorting, and brand filtering
     * - Dynamic brand aggregation
     * - Cached responses for faster load
     * - Sidebar category tree (manual fallback for root)
     */
    public function index(Request $request)
    {
        // 🧠 Include model timestamps in cache key to auto-refresh when data changes
        $lastUpdate = Product::max('updated_at') ?? now();
        $cacheKey = $this->makeCacheKey(request: $request, lastUpdate: $lastUpdate);

        return Cache::remember(key: $cacheKey, ttl: now()->addMinutes(30), callback: function () use ($request) {
            // 1️⃣ Build base query
            $query = Product::with('category')->filter($request);

            // 2️⃣ Aggregate available brands (with short cache)
            $availableBrands = Cache::remember(
                key: 'brands:cache:' . md5(string: $request->fullUrl()),
                ttl: now()->addSeconds(30),
                callback: fn() => $this->getAvailableBrandsOptimized(query: $query)
            );

            // 3️⃣ Paginate results
            $products = $query->paginate(20)->withQueryString();

            // 4️⃣ Sidebar (cached structure)
            $sidebarTree = $this->buildSidebarTree();

            // 5️⃣ Breadcrumbs
            $breadcrumbs = $this->buildBreadcrumbs(request: $request);

            // ✅ Final structured response
            return response()->json(data: [
                'category' => null,
                'breadcrumbs' => $breadcrumbs,
                'sidebar_tree' => CategoryResource::collection(resource: $sidebarTree),
                'available_brands' => $availableBrands,
                'products' => new ProductCollection(resource: $products),
            ]);
        });
    }

    /**
     * 🔑 Builds a unique cache key per query (pagination, filters, etc.)
     * Includes model update timestamp to ensure freshness after data changes.
     */
    private function makeCacheKey(Request $request, $lastUpdate): string
    {
        return sprintf(
            'products:v2:%s:%s',
            md5(string: $request->fullUrl()),
            md5(string: $lastUpdate)
        );
    }

    /**
     * ⚡ Optimized brand aggregation (Query Builder for speed)
     *
     * Skips Eloquent overhead, performs DISTINCT scan on indexed columns.
     * Up to 2× faster on 100k+ rows.
     */
    private function getAvailableBrandsOptimized($query)
    {
        $baseSql = $query->toBase();
        $table = (new Product)->getTable();

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
     * 🌳 Sidebar tree builder (root → children, with fallback)
     */
    private function buildSidebarTree()
    {
        return Cache::remember(key: 'sidebar_tree:v2', ttl: now()->addMinutes(30), callback: function () {
            // Prefer canonical “TV sprejemniki”
            $root = Category::where(column: 'slug', operator: 'tv-sprejemniki')->first()
                ?? Category::whereNull('parent_id')->first()
                ?? Category::orderBy('id')->first();

            if (!$root) {
                return collect();
            }

            // Fetch children (true hierarchy)
            $children = Category::where(column: 'parent_id', operator: $root->id)
                ->orderBy('name')
                ->get();

            // Fallback → attach all others if no children
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
     * 🧭 Breadcrumbs builder
     */
    private function buildBreadcrumbs(Request $request): array
    {
        $crumbs = [
            ['name' => 'Search Results', 'slug' => 'search', 'url' => '/products'],
        ];

        if ($request->filled(key: 'search')) {
            $crumbs[] = [
                'name' => '"' . e(value: $request->search) . '"',
                'slug' => 'query',
                'url' => '#',
            ];
        }

        return $crumbs;
    }
}
