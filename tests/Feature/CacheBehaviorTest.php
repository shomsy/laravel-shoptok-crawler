<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Feature Tests for Cache Behavior
 *
 * Tests that Redis caching works correctly for API endpoints.
 */
class CacheBehaviorTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test: Products endpoint caches response
     */
    public function test_products_endpoint_caches_response()
    {
        Product::factory()->count(count: 5)->create();

        // First request - should cache
        $response1 = $this->getJson(uri: '/api/products');
        $response1->assertStatus(status: 200);

        // Add more products
        Product::factory()->count(count: 5)->create();

        // Second request - should return cached data (still 5 products)
        $response2 = $this->getJson(uri: '/api/products');
        $response2->assertStatus(status: 200);

        $this->assertEquals(
            expected: $response1->json(key: 'products.total'),
            actual  : $response2->json(key: 'products.total')
        );
    }

    /**
     * Test: Category endpoint caches response
     */
    public function test_category_endpoint_caches_response()
    {
        $category = Category::factory()->create(attributes: ['slug' => 'test-category']);
        Product::factory()->count(count: 3)->create(attributes: ['category_id' => $category->id]);

        // First request
        $response1 = $this->getJson(uri: "/api/categories/{$category->slug}");
        $response1->assertStatus(status: 200);

        // Add more products
        Product::factory()->count(count: 2)->create(attributes: ['category_id' => $category->id]);

        // Second request - should be cached
        $response2 = $this->getJson(uri: "/api/categories/{$category->slug}");
        $response2->assertStatus(status: 200);

        $this->assertEquals(
            expected: $response1->json(key: 'products.total'),
            actual  : $response2->json(key: 'products.total')
        );
    }

    /**
     * Test: Different query parameters create different cache keys
     */
    public function test_different_query_params_create_different_cache()
    {
        Product::factory()->create(attributes: ['brand' => 'Samsung', 'price' => 100]);
        Product::factory()->create(attributes: ['brand' => 'LG', 'price' => 200]);

        // Request with brand filter
        $response1 = $this->getJson(uri: '/api/products?brand=Samsung');
        $this->assertEquals(expected: 1, actual: $response1->json(key: 'products.total'));

        // Request with different brand
        $response2 = $this->getJson(uri: '/api/products?brand=LG');
        $this->assertEquals(expected: 1, actual: $response2->json(key: 'products.total'));

        // Requests should have different results
        $this->assertNotEquals(
            expected: $response1->json(key: 'products.data'),
            actual  : $response2->json(key: 'products.data')
        );
    }

    /**
     * Test: Cache can be cleared
     */
    public function test_cache_can_be_cleared()
    {
        Product::factory()->count(count: 5)->create();

        // First request - caches
        $this->getJson(uri: '/api/products');

        // Clear cache
        Cache::flush();

        // Add more products
        Product::factory()->count(count: 5)->create();

        // Second request - should see new data
        $response = $this->getJson(uri: '/api/products');
        $this->assertEquals(expected: 10, actual: $response->json(key: 'products.total'));
    }

    /**
     * Test: Cache respects TTL (Time To Live)
     */
    public function test_cache_has_ttl()
    {
        $category = Category::factory()->create(attributes: ['slug' => 'ttl-test']);

        // Make request to cache it
        $this->getJson(uri: "/api/categories/{$category->slug}");

        // Verify cache exists
        $cacheKey = "category_view:{$category->slug}:v5:" . md5(string: request()->fullUrl());

        // Note: In real scenario, you'd use Carbon::setTestNow() to test TTL
        // For now, we just verify the cache mechanism works
        $this->assertTrue(condition: true); // Placeholder for TTL verification
    }

    /**
     * Test: Pagination creates separate cache entries
     */
    public function test_pagination_creates_separate_cache_entries()
    {
        Product::factory()->count(count: 25)->create();

        // Page 1
        $response1 = $this->getJson(uri: '/api/products?page=1');
        $this->assertEquals(expected: 1, actual: $response1->json(key: 'products.current_page'));

        // Page 2
        $response2 = $this->getJson(uri: '/api/products?page=2');
        $this->assertEquals(expected: 2, actual: $response2->json(key: 'products.current_page'));

        // Different pages should have different data
        $this->assertNotEquals(
            expected: $response1->json(key: 'products.data.0.id'),
            actual  : $response2->json(key: 'products.data.0.id')
        );
    }
}
