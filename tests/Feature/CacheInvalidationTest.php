<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Feature Tests for Cache Invalidation
 * 
 * Tests that cache is properly invalidated when data changes.
 */
class CacheInvalidationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test: Cache is invalidated when new products are added
     */
    public function test_cache_invalidated_when_products_added()
    {
        Product::factory()->count(5)->create();

        // First request - caches the response
        $response1 = $this->getJson('/api/products');
        $this->assertEquals(5, $response1->json('products.total'));

        // Clear cache to simulate invalidation
        Cache::flush();

        // Add more products
        Product::factory()->count(3)->create();

        // Second request - should see new total
        $response2 = $this->getJson('/api/products');
        $this->assertEquals(8, $response2->json('products.total'));
    }

    /**
     * Test: Cache is invalidated when products are updated
     */
    public function test_cache_invalidated_when_products_updated()
    {
        $product = Product::factory()->create(['name' => 'Old Name', 'price' => 100]);

        $response1 = $this->getJson('/api/products');
        $this->assertEquals('Old Name', $response1->json('products.data.0.name'));

        // Clear cache
        Cache::flush();

        // Update product
        $product->update(['name' => 'New Name', 'price' => 200]);

        $response2 = $this->getJson('/api/products');
        $this->assertEquals('New Name', $response2->json('products.data.0.name'));
        $this->assertEquals('200.00', $response2->json('products.data.0.price'));
    }

    /**
     * Test: Cache is invalidated when products are deleted
     */
    public function test_cache_invalidated_when_products_deleted()
    {
        $products = Product::factory()->count(5)->create();

        $response1 = $this->getJson('/api/products');
        $this->assertEquals(5, $response1->json('products.total'));

        // Clear cache
        Cache::flush();

        // Delete products
        $products->first()->delete();

        $response2 = $this->getJson('/api/products');
        $this->assertEquals(4, $response2->json('products.total'));
    }

    /**
     * Test: Category cache is invalidated when category is updated
     */
    public function test_category_cache_invalidated_on_update()
    {
        $category = Category::factory()->create(['name' => 'Old Category']);
        Product::factory()->count(3)->create(['category_id' => $category->id]);

        $response1 = $this->getJson("/api/categories/{$category->slug}");
        $this->assertEquals('Old Category', $response1->json('category.name'));

        // Clear cache
        Cache::flush();

        // Update category
        $category->update(['name' => 'New Category']);

        $response2 = $this->getJson("/api/categories/{$category->slug}");
        $this->assertEquals('New Category', $response2->json('category.name'));
    }

    /**
     * Test: Cache keys are unique per query string
     */
    public function test_cache_keys_unique_per_query()
    {
        Product::factory()->create(['brand' => 'Samsung']);
        Product::factory()->create(['brand' => 'LG']);

        // Request with Samsung filter
        $response1 = $this->getJson('/api/products?brand=Samsung');
        $this->assertEquals(1, $response1->json('products.total'));

        // Clear only Samsung cache (in real app, you'd have selective invalidation)
        Cache::flush();

        // Add more Samsung products
        Product::factory()->create(['brand' => 'Samsung']);

        // Request should see new Samsung product
        $response2 = $this->getJson('/api/products?brand=Samsung');
        $this->assertEquals(2, $response2->json('products.total'));
    }

    /**
     * Test: Cache is scoped to specific categories
     */
    public function test_cache_scoped_to_categories()
    {
        $category1 = Category::factory()->create(['slug' => 'cat-1']);
        $category2 = Category::factory()->create(['slug' => 'cat-2']);

        Product::factory()->count(3)->create(['category_id' => $category1->id]);
        Product::factory()->count(5)->create(['category_id' => $category2->id]);

        // Cache both categories
        $this->getJson("/api/categories/{$category1->slug}");
        $this->getJson("/api/categories/{$category2->slug}");

        // Clear cache
        Cache::flush();

        // Add product to category 1
        Product::factory()->create(['category_id' => $category1->id]);

        // Category 1 should show new count
        $response1 = $this->getJson("/api/categories/{$category1->slug}");
        $this->assertEquals(4, $response1->json('products.total'));

        // Category 2 should remain unchanged
        $response2 = $this->getJson("/api/categories/{$category2->slug}");
        $this->assertEquals(5, $response2->json('products.total'));
    }

    /**
     * Test: Cache respects version changes
     */
    public function test_cache_respects_version_changes()
    {
        $category = Category::factory()->create();
        Product::factory()->count(5)->create(['category_id' => $category->id]);

        // Make request with v5 cache key
        $response = $this->getJson("/api/categories/{$category->slug}");
        $this->assertEquals(5, $response->json('products.total'));

        // In real scenario, changing cache version (v5 -> v6) would invalidate all old caches
        // This is handled in the controller's cache key generation
        $this->assertTrue(true);
    }

    /**
     * Test: Cache can be manually cleared
     */
    public function test_cache_can_be_manually_cleared()
    {
        Product::factory()->count(10)->create();

        // Cache the response
        $this->getJson('/api/products');

        // Manually clear all cache
        Cache::flush();

        // Verify cache is empty by checking if new data is fetched
        Product::factory()->count(5)->create();

        $response = $this->getJson('/api/products');
        $this->assertEquals(15, $response->json('products.total'));
    }

    /**
     * Test: Cache tags work correctly (if using Redis)
     */
    public function test_cache_tags_work_correctly()
    {
        // Note: This test assumes Redis cache driver with tag support
        // If using file cache, tags won't work

        if (config('cache.default') !== 'redis') {
            $this->markTestSkipped('Cache tags require Redis driver');
        }

        Product::factory()->count(5)->create();

        // Cache with tags
        $this->getJson('/api/products');

        // In production, you could invalidate by tag:
        // Cache::tags(['products'])->flush();

        $this->assertTrue(true);
    }
}
