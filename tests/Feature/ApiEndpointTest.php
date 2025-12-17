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
        $response = $this->getJson('/api/products');

        $response->assertStatus(200)
            ->assertJson([
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
        $response = $this->getJson('/api/categories/non-existent-category');

        $response->assertStatus(404);
    }

    /**
     * Test: Products endpoint search functionality
     */
    public function test_products_search_filters_correctly()
    {
        Product::factory()->create(['name' => 'Samsung TV 55 inch']);
        Product::factory()->create(['name' => 'LG Monitor 27 inch']);
        Product::factory()->create(['name' => 'Samsung Phone']);

        $response = $this->getJson('/api/products?search=Samsung');

        $response->assertStatus(200);
        $data = $response->json('products.data');

        $this->assertCount(2, $data);
        foreach ($data as $product) {
            $this->assertStringContainsStringIgnoringCase('Samsung', $product['name']);
        }
    }

    /**
     * Test: Products endpoint handles multiple filters
     */
    public function test_products_handles_multiple_filters()
    {
        Product::factory()->create([
            'name' => 'Samsung TV',
            'brand' => 'Samsung',
            'price' => 500,
        ]);
        Product::factory()->create([
            'name' => 'LG TV',
            'brand' => 'LG',
            'price' => 600,
        ]);

        $response = $this->getJson('/api/products?brand=Samsung&sort=price_asc');

        $response->assertStatus(200);
        $this->assertEquals(1, $response->json('products.meta.total'));
        $this->assertEquals('Samsung', $response->json('products.data.0.brand'));
    }

    /**
     * Test: Category endpoint shows correct breadcrumbs
     */
    public function test_category_shows_correct_breadcrumbs()
    {
        $root = Category::factory()->create([
            'name' => 'Electronics',
            'slug' => 'electronics',
            'parent_id' => null,
        ]);

        $child = Category::factory()->create([
            'name' => 'TVs',
            'slug' => 'tvs',
            'parent_id' => $root->id,
        ]);

        $response = $this->getJson("/api/categories/{$child->slug}");

        $response->assertStatus(200);

        $breadcrumbs = $response->json('breadcrumbs');
        $this->assertIsArray($breadcrumbs);
        $this->assertGreaterThanOrEqual(1, count($breadcrumbs));
    }

    /**
     * Test: Products endpoint pagination works correctly
     */
    public function test_products_pagination_works()
    {
        Product::factory()->count(25)->create();

        // First page
        $response1 = $this->getJson('/api/products?page=1');
        $response1->assertStatus(200);
        $this->assertEquals(20, count($response1->json('products.data')));
        $this->assertEquals(1, $response1->json('products.meta.current_page'));

        // Second page
        $response2 = $this->getJson('/api/products?page=2');
        $response2->assertStatus(200);
        $this->assertEquals(5, count($response2->json('products.data')));
        $this->assertEquals(2, $response2->json('products.meta.current_page'));
    }

    /**
     * Test: Available brands are correctly aggregated
     */
    public function test_available_brands_aggregation()
    {
        Product::factory()->create(['brand' => 'Samsung']);
        Product::factory()->create(['brand' => 'LG']);
        Product::factory()->create(['brand' => 'Samsung']); // Duplicate
        Product::factory()->create(['brand' => null]); // No brand

        $response = $this->getJson('/api/products');

        $response->assertStatus(200);
        $brands = $response->json('available_brands');

        $this->assertIsArray($brands);
        $this->assertContains('Samsung', $brands);
        $this->assertContains('LG', $brands);
        $this->assertCount(2, $brands); // Only unique brands
    }

    /**
     * Test: Category endpoint filters products by category
     */
    public function test_category_filters_products_correctly()
    {
        $category1 = Category::factory()->create(['slug' => 'category-1']);
        $category2 = Category::factory()->create(['slug' => 'category-2']);

        Product::factory()->count(5)->create(['category_id' => $category1->id]);
        Product::factory()->count(3)->create(['category_id' => $category2->id]);

        $response = $this->getJson("/api/categories/{$category1->slug}");

        $response->assertStatus(200);
        $this->assertEquals(5, $response->json('products.meta.total'));
    }

    /**
     * Test: Sidebar tree structure is correct
     */
    public function test_sidebar_tree_structure()
    {
        $root = Category::factory()->create(['parent_id' => null]);
        Category::factory()->count(2)->create(['parent_id' => $root->id]);

        $response = $this->getJson('/api/products');

        $response->assertStatus(200);
        $sidebar = $response->json('sidebar_tree');

        $this->assertIsArray($sidebar);
        $this->assertNotEmpty($sidebar);
    }

    /**
     * Test: Price sorting ascending works
     */
    public function test_price_sorting_ascending()
    {
        Product::factory()->create(['name' => 'Expensive', 'price' => 1000]);
        Product::factory()->create(['name' => 'Cheap', 'price' => 100]);
        Product::factory()->create(['name' => 'Medium', 'price' => 500]);

        $response = $this->getJson('/api/products?sort=price_asc');

        $response->assertStatus(200);
        $data = $response->json('products.data');

        $this->assertEquals('Cheap', $data[0]['name']);
        $this->assertEquals('Medium', $data[1]['name']);
        $this->assertEquals('Expensive', $data[2]['name']);
    }

    /**
     * Test: Price sorting descending works
     */
    public function test_price_sorting_descending()
    {
        Product::factory()->create(['name' => 'Expensive', 'price' => 1000]);
        Product::factory()->create(['name' => 'Cheap', 'price' => 100]);
        Product::factory()->create(['name' => 'Medium', 'price' => 500]);

        $response = $this->getJson('/api/products?sort=price_desc');

        $response->assertStatus(200);
        $data = $response->json('products.data');

        $this->assertEquals('Expensive', $data[0]['name']);
        $this->assertEquals('Medium', $data[1]['name']);
        $this->assertEquals('Cheap', $data[2]['name']);
    }
}
