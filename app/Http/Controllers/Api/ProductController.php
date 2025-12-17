<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;

final class ProductController extends Controller
{
    public function index(Request $request)
    {
        // 🔒 Caching Strategy:
        // We act as a "read-through" cache.
        // Key includes all query parameters (filtering + pagination).
        $cacheKey = "products:global:v1:" . md5($request->fullUrl());

        return \Illuminate\Support\Facades\Cache::remember($cacheKey, now()->addMinutes(30), function () use ($request) {

            // 1. Base Query
            $productsQuery = Product::query()
                ->with(['category']); // Eager load category

            // 2. Filter (Search, Brand, Sort)
            // Using the scope defined in Model
            $productsQuery->filter($request);

            // 3. Dynamic Brands (Aggregation for current filtered set)
            // We need to clone the query to get distinctive brands without pagination
            $brandQuery = clone $productsQuery;
            // Reset orders/limits for aggregation
            $brandQuery->getQuery()->orders = null;
            $availableBrands = $brandQuery->whereNotNull('brand')
                ->distinct()
                ->orderBy('brand')
                ->pluck('brand');

            // 4. Pagination (20 per page as requested)
            $products = $productsQuery->paginate(20)->withQueryString();

            // 5. Sidebar (Show ALL Categories)
            // User requested to see all 3 specific categories immediately.
            // We fetch all categories to ignore potential broken parent_id links in the DB.
            // 5. Sidebar (Manual Hierarchy Enforcement 🌲)
            // The DB has messed up relationships (2<->3, 1 orphan).
            // User wants "TV Sprejemniki" (slug: tv-sprejemniki) to be the Parent, and others to be Subcategories.
            $rootSlug = 'tv-sprejemniki';
            $root = \App\Models\Category::where('slug', $rootSlug)->first();

            if ($root) {
                // Fetch everything else as "children"
                $others = \App\Models\Category::where('slug', '!=', $rootSlug)
                    ->orderBy('name')
                    ->get();

                $root->children = $others;
                $sidebarTree = collect([$root]);
            } else {
                // Fallback if root missing
                $sidebarTree = \App\Models\Category::query()
                    ->orderBy('name')
                    ->get()
                    ->map(fn($c) => $c->children = []);
            }

            // 6. Breadcrumbs
            $breadcrumbs = [
                ['name' => 'Search Results', 'slug' => 'search', 'url' => '/products']
            ];
            if ($request->filled('search')) {
                $breadcrumbs[] = ['name' => '"' . $request->search . '"', 'slug' => 'query', 'url' => '#'];
            }

            return response()->json([
                'category' => null, // No specific category context
                'breadcrumbs' => $breadcrumbs,
                'sidebar_tree' => $sidebarTree,
                'available_brands' => $availableBrands,
                'products' => $products,
            ]);
        });
    }
}
