<?php

use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ProductController;
use Illuminate\Support\Facades\Route;

Route::get(uri: '/products', action: [ProductController::class, 'index']);
Route::get(uri: '/categories', action: [CategoryController::class, 'index']);
Route::get(uri: '/categories/{slug}', action: [CategoryController::class, 'show']);
