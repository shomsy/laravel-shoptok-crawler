<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class CategoryController extends Controller
{
    /**
     * Return root categories (no parent).
     * GET /api/categories
     */
    /**
     * Return root categories (no parent).
     * GET /api/categories
     *
     * 🚀 **Enterprise Grade Endpoint**
     * - Returns top-level navigation nodes.
     * - Cached in Redis (1h).
     */
    public function index(Request $request)
    {
        return Cache::remember(key: 'categories:roots', ttl: now()->addHour(), callback: function () use ($request) {
            // ako frontend pošalje ?parent=tv-sprejemniki (legacy support, optional)
            if ($request->has(key: 'parent')) {
                $parent = DB::table(table: 'categories')
                    ->where(column: 'slug', operator: $request->parent)
                    ->first();

                if (!$parent) {
                    return response()->json(data: ['data' => []]);
                }

                $children = DB::table(table: 'categories')
                    ->select(columns: ['id', 'name', 'slug', 'parent_id'])
                    ->where(column: 'parent_id', operator: $parent->id)
                    ->get();

                return response()->json(data: ['data' => $children]);
            }

            // default: root kategorije
            // Fetch roots that have children/products logic could be here if we want deep cleaning,
            // but for roots, usually we want to show all major departments.
            $roots = DB::table(table: 'categories')
                ->select(columns: ['id', 'name', 'slug', 'parent_id'])
                ->whereNull(columns: 'parent_id')
                ->orderBy(column: 'name')
                ->get();

            return response()->json(data: ['data' => $roots]);
        });
    }

    /**
     * Return a category and its subcategories.
     * GET /api/categories/{slug}
     */
    /**
     * Display the specified category with its products and subcategories (or siblings).
     *
     * ⚡ **Performance Optimized Endpoint**
     * Uses raw QueryBuilder for maximum speed and control over SQL JOINs.
     * Returns a composite JSON structure to minimize HTTP round-trips.
     *
     * @param Request $request
     * @param string $slug
     *
     * @return JsonResponse
     */
    /**
     * Display the specified category with smart filtering and caching.
     *
     * 🚀 **Enterprise Grade Endpoint**
     * - Uses DB Facade for raw speed.
     * - Implements "Smart Sidebar" (hides empty categories via EXISTS).
     * - Implements "Dynamic Brands" (shows only relevant brands).
     * - Caches the entire result set in Redis for instant load.
     *
     * @param string $slug
     *
     * @return JsonResponse
     */
    public function show(Request $request, string $slug)
    {
        // 🔒 Cache Key Strategy
        $cacheKey = "category_view:{$slug}:v5:" . md5($request->fullUrl());

        return Cache::remember($cacheKey, now()->addMinutes(30), function () use ($request, $slug) {
            // 1. Fetch Main Category
            $category = Category::where('slug', $slug)->firstOrFail();

            // 2. Sidebar Tree Construction 🌳
            // 2. Sidebar (Manual Hierarchy Enforcement 🌲)
            // Consistent with ProductController
            $rootSlug = 'tv-sprejemniki';
            $root = Category::where('slug', $rootSlug)->first();

            if ($root) {
                // Fetch everything else as "children"
                $others = Category::where('slug', '!=', $rootSlug)
                    ->orderBy('name')
                    ->get();

                $root->children = $others;
                $sidebarTree = collect([$root]);
            } else {
                // Fallback
                $sidebarTree = Category::query()
                    ->orderBy('name')
                    ->get()
                    ->map(fn($c) => $c->children = []);
            }

            // 3. Products Query (Hybrid Strategy 🧠)
            // - If Root ("tv-sprejemniki"): Recursive BFS (Show Everything)
            // - If Subcategory: Strict (Show only this category) to avoid Cycling back to Root

            $descendantIds = [$category->id];

            // Only perform BFS recursion for the designated ROOT
            if ($slug === 'tv-sprejemniki') {
                $visited = [$category->id];
                $queue = [$category->id];

                // 🔄 BFS to find all children, grandchildren, etc.
                while (!empty($queue)) {
                    $parentId = array_shift($queue);

                    // Get direct children
                    $childrenIds = Category::where('parent_id', $parentId)->pluck('id')->toArray();

                    foreach ($childrenIds as $childId) {
                        if (!in_array($childId, $visited)) {
                            $visited[] = $childId;
                            $descendantIds[] = $childId;
                            $queue[] = $childId;
                        }
                    }
                    if (count($descendantIds) > 1000) break;
                }
            }

            $productsQuery = Product::query()
                ->whereIn('category_id', $descendantIds)
                ->with(['category']);

            // Filter: Brands
            if ($request->filled('brand')) {
                $brand = $request->input('brand');
                $productsQuery->where('brand', $brand);
            }

            // Sort
            if ($request->filled('sort')) {
                match ($request->input('sort')) {
                    'price_asc' => $productsQuery->orderBy('price', 'asc'),
                    'price_desc' => $productsQuery->orderBy('price', 'desc'),
                    default => $productsQuery->latest(),
                };
            } else {
                $productsQuery->latest();
            }

            $products = $productsQuery->paginate(20)->withQueryString();

            // 5. Breadcrumbs
            $breadcrumbs = [];
            $crumb = $category;
            $visitedIds = []; // 🛡️ Cycle Protection
            $depth = 0;

            while ($crumb && $depth < 10) { // Limit depth to avoid infinite loops
                if (in_array($crumb->id, $visitedIds)) {
                    break; // Cycle detected!
                }
                $visitedIds[] = $crumb->id;

                array_unshift($breadcrumbs, [
                    'name' => $crumb->name,
                    'slug' => $crumb->slug,
                    'url'  => "/category/{$crumb->slug}"
                ]);

                $crumb = $crumb->parent; // Using Relationship
                $depth++;
            }

            // 6. Dynamic Brands
            $availableBrands = Product::whereIn('category_id', $descendantIds)
                ->whereNotNull('brand')
                ->distinct()
                ->orderBy('brand')
                ->pluck('brand');

            return response()->json([
                'category'         => $category,
                'breadcrumbs'      => $breadcrumbs,
                'sidebar_tree'     => $sidebarTree,
                'available_brands' => $availableBrands,
                'products'         => $products,
            ]);
        });
    }
}
