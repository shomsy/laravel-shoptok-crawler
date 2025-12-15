<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;

final class CategoryController extends Controller
{
    public function index()
    {
        return response()->json(
            Category::with('children')
                ->whereNull('parent_id')
                ->get()
        );
    }

    public function show(string $slug)
    {
        return response()->json(
            Category::where('slug', $slug)
                ->with('children')
                ->firstOrFail()
        );
    }
}
