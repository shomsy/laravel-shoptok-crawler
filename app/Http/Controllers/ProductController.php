<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * 🛒 **ProductController**
 *
 * This controller handles the **frontend display** of all crawled products.
 *
 * 🧩 What it does:
 * - Fetches products from the database.
 * - Supports optional category filtering via query parameter (?category=slug).
 * - Paginates results (20 per page).
 * - Builds the left sidebar with subcategories under "TV sprejemniki".
 * - Passes everything to the Blade view for rendering.
 *
 * 🧠 Why this exists:
 * - Keeps the UI layer separate from crawling logic.
 * - Provides a clean, minimal, “read-only” interface for visitors.
 * - Matches the Shoptok category structure but uses your own DB data.
 */
final class ProductController extends Controller
{
    /**
     * 🖼️ Displays the product listing page.
     *
     * Example URLs:
     * ```
     * /products
     * /products?category=televizorji
     * ```
     *
     * @param Request $request The incoming HTTP request containing optional filters.
     *
     * @return View The rendered view with products and sidebar data.
     */
    public function index(Request $request) : View
    {
        // 🏷️ Step 1: Read the optional category filter from query (?category=slug).
        $categorySlug = $request->string(key: 'category')->toString();

        // 📦 Step 2: Start a product query (with eager-loaded category relation).
        $query = Product::query()->with(relations: 'category');

        // 🔍 Step 3: If category is specified, filter only those products.
        if ($categorySlug !== '') {
            $query->whereHas(
                relation: 'category',
                callback: static fn ($q) => $q->where('slug', $categorySlug)
            );
        }

        // 📑 Step 4: Apply ordering and pagination (20 items per page).
        $products = $query
            ->orderBy(column: 'id')
            ->paginate(perPage: 20)
            ->withQueryString();

        // 🧭 Step 5: Build the sidebar — subcategories of “TV sprejemniki”.
        $root = Category::where('slug', 'tv-sprejemniki')->first();

        $sidebarCategories = $root
            ? Category::where('parent_id', $root->id)
                ->orderBy('name')
                ->get()
            : collect();

        // 🎨 Step 6: Render the Blade view with data.
        // View: resources/views/products/index.blade.php
        return view(view: 'products.index', data: [
            'products'          => $products,
            'sidebarCategories' => $sidebarCategories,
            'activeCategory'    => $categorySlug,
        ]);
    }
}
