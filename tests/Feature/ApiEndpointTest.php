<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature Tests for API Endpoints - Additional Coverage
 *
 * Tests edge cases, error handling, and additional scenarios.
 */
class ApiEndpointTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test: Products endpoint handles empty database
     */
    public function test_products_endpoint_handles_empty_database()
    {
        $response = $this->getJson(uri: '/api/products');

        $response->assertStatus(status: 200)
            ->assertJson(value: [
                                    'products' => [
                                        'meta' => [
                                            'total' => 0,
                                        ],
                                        'data' => [],
                                    ],
                                ]);
    }


    /**
     * Test: Category endpoint returns 404 for non-existent category
     */
    public function test_category_endpoint_returns_404_for_invalid_slug()
    {
        $response = $this->getJson(uri: '/api/categories/non-existent-category');

        $response->assertStatus(status: 404);
    }

    /**
     * Test: Products endpoint search functionality
     */
    public function test_products_search_filters_correctly()
    {
        Product::factory()->create(attributes: ['name' => 'Samsung TV 55 inch']);
        Product::factory()->create(attributes: ['name' => 'LG Monitor 27 inch']);
        Product::factory()->create(attributes: ['name' => 'Samsung Phone']);

        $response = $this->getJson(uri: '/api/products?search=Samsung');

        $response->assertStatus(status: 200);
        $data = $response->json(key: 'products.data');

        $this->assertCount(expectedCount: 2, haystack: $data);
        foreach ($data as $product) {
            $this->assertStringContainsStringIgnoringCase(needle: 'Samsung', haystack: $product['name']);
        }
    }

    /**
     * Test: Products endpoint handles multiple filters
     */
    public function test_products_handles_multiple_filters()
    {
        Product::factory()->create(attributes: ['name' => 'Samsung TV', 'brand' => 'Samsung', 'price' => 500]);
        Product::factory()->create(attributes: ['name' => 'LG TV', 'brand' => 'LG', 'price' => 600]);

        $response = $this->getJson(uri: '/api/products?brand=Samsung&sort=price_asc');

        $response->assertStatus(status: 200);
        $this->assertEquals(expected: 1, actual: $response->json(key: 'products.meta.total'));
        $this->assertEquals(expected: 'Samsung', actual: $response->json(key: 'products.data.0.brand'));
    }

    /**
     * Test: Category endpoint shows correct breadcrumbs
     */
    public function test_category_shows_correct_breadcrumbs()
    {
        $root  = Category::factory()->create(attributes: ['name' => 'Electronics', 'slug' => 'electronics', 'parent_id' => null]);
        $child = Category::factory()->create(attributes: ['name' => 'TVs', 'slug' => 'tvs', 'parent_id' => $root->id]);

        $response = $this->getJson(uri: "/api/categories/{$child->slug}");

        $response->assertStatus(status: 200);

        $breadcrumbs = $response->json(key: 'breadcrumbs');
        $this->assertIsArray(actual: $breadcrumbs);
        $this->assertGreaterThanOrEqual(minimum: 1, actual: count(value: $breadcrumbs));
    }

    /**
     * Test: Products endpoint pagination works correctly
     */
    public function test_products_pagination_works()
    {
        Product::factory()->count(count: 25)->create();

        // First page
        $response1 = $this->getJson(uri: '/api/products?page=1');
        $response1->assertStatus(status: 200);
        $this->assertEquals(expected: 20, actual: count(value: $response1->json(key: 'products.data')));
        $this->assertEquals(expected: 1, actual: $response1->json(key: 'products.meta.current_page'));

        // Second page
        $response2 = $this->getJson(uri: '/api/products?page=2');
        $response2->assertStatus(status: 200);
        $this->assertEquals(expected: 5, actual: count(value: $response2->json(key: 'products.data')));
        $this->assertEquals(expected: 2, actual: $response2->json(key: 'products.meta.current_page'));
    }

    /**
     * Test: Available brands are correctly aggregated
     */
    public function test_available_brands_aggregation()
    {
        Product::factory()->create(attributes: ['brand' => 'Samsung']);
        Product::factory()->create(attributes: ['brand' => 'LG']);
        Product::factory()->create(attributes: ['brand' => 'Samsung']); // Duplicate
        Product::factory()->create(attributes: ['brand' => null]); // No brand

        $response = $this->getJson(uri: '/api/products');

        $response->assertStatus(status: 200);
        $brands = $response->json(key: 'available_brands');

        $this->assertIsArray(actual: $brands);
        $this->assertContains(needle: 'Samsung', haystack: $brands);
        $this->assertContains(needle: 'LG', haystack: $brands);
        $this->assertCount(expectedCount: 2, haystack: $brands); // Only unique brands
    }

    /**
     * Test: Category endpoint filters products by category
     */
    public function test_category_filters_products_correctly()
    {
        $category1 = Category::factory()->create(attributes: ['slug' => 'category-1']);
        $category2 = Category::factory()->create(attributes: ['slug' => 'category-2']);

        Product::factory()->count(count: 5)->create(attributes: ['category_id' => $category1->id]);
        Product::factory()->count(count: 3)->create(attributes: ['category_id' => $category2->id]);

        $response = $this->getJson(uri: "/api/categories/{$category1->slug}");

        $response->assertStatus(status: 200);
        $this->assertEquals(expected: 5, actual: $response->json(key: 'products.meta.total'));
    }

    /**
     * Test: Sidebar tree structure is correct
     */
    public function test_sidebar_tree_structure()
    {
        $root = Category::factory()->create(attributes: ['parent_id' => null]);
        Category::factory()->count(count: 2)->create(attributes: ['parent_id' => $root->id]);

        $response = $this->getJson(uri: '/api/products');

        $response->assertStatus(status: 200);
        $sidebar = $response->json(key: 'sidebar_tree');

        $this->assertIsArray(actual: $sidebar);
        $this->assertNotEmpty(actual: $sidebar);
    }

    /**
     * Test: Price sorting ascending works
     */
    public function test_price_sorting_ascending()
    {
        Product::factory()->create(attributes: ['name' => 'Expensive', 'price' => 1000]);
        Product::factory()->create(attributes: ['name' => 'Cheap', 'price' => 100]);
        Product::factory()->create(attributes: ['name' => 'Medium', 'price' => 500]);

        $response = $this->getJson(uri: '/api/products?sort=price_asc');

        $response->assertStatus(status: 200);
        $data = $response->json(key: 'products.data');

        $this->assertEquals(expected: 'Cheap', actual: $data[0]['name']);
        $this->assertEquals(expected: 'Medium', actual: $data[1]['name']);
        $this->assertEquals(expected: 'Expensive', actual: $data[2]['name']);
    }

    /**
     * Test: Price sorting descending works
     */
    public function test_price_sorting_descending()
    {
        Product::factory()->create(attributes: ['name' => 'Expensive', 'price' => 1000]);
        Product::factory()->create(attributes: ['name' => 'Cheap', 'price' => 100]);
        Product::factory()->create(attributes: ['name' => 'Medium', 'price' => 500]);

        $response = $this->getJson(uri: '/api/products?sort=price_desc');

        $response->assertStatus(status: 200);
        $data = $response->json(key: 'products.data');

        $this->assertEquals(expected: 'Expensive', actual: $data[0]['name']);
        $this->assertEquals(expected: 'Medium', actual: $data[1]['name']);
        $this->assertEquals(expected: 'Cheap', actual: $data[2]['name']);
    }
}
