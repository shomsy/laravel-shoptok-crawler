<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ProductFilterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush(); // Ensure fresh state
    }

    /**
     * Test logic: Index endpoint returns 200 and paginated structure.
     */
    public function test_products_endpoint_returns_paginated_list()
    {
        Product::factory()->count(25)->create();

        $response = $this->getJson('/api/products');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'products' => [
                    'data' => [
                        '*' => ['id', 'name', 'price', 'brand', 'image_url']
                    ],
                    'links',
                ],
                'sidebar_tree'
            ]);

        // Pagination Limit Check
        $this->assertEquals(20, $response->json('products.per_page'));
        $this->assertEquals(25, $response->json('products.total'));
    }

    /**
     * Test logic: Brand Filtering works correctly.
     */
    public function test_filter_by_brand()
    {
        $category = Category::factory()->create();

        // Product we want
        Product::factory()->create(['brand' => 'Samsung', 'category_id' => $category->id]);

        // Product we don't want
        Product::factory()->create(['brand' => 'Apple', 'category_id' => $category->id]);

        // Act: Filter by Samsung
        $response = $this->getJson('/api/products?brand=Samsung');

        $response->assertStatus(200);
        $this->assertEquals(1, $response->json('products.total'));
        $this->assertEquals('Samsung', $response->json('products.data.0.brand'));
    }

    /**
     * Test logic: Sorting by Price ASC works.
     */
    public function test_sort_by_price_asc()
    {
        Product::factory()->create(['price' => 100, 'name' => 'Expensive']);
        Product::factory()->create(['price' => 10, 'name' => 'Cheap']);

        $response = $this->getJson('/api/products?sort=price_asc');

        $response->assertStatus(200);

        $data = $response->json('products.data');
        $this->assertEquals('Cheap', $data[0]['name']);
        $this->assertEquals('Expensive', $data[1]['name']);
    }

    /**
     * Test logic: Sorting by Price DESC works.
     */
    public function test_sort_by_price_desc()
    {
        Product::factory()->create(['price' => 10, 'name' => 'Cheap']);
        Product::factory()->create(['price' => 100, 'name' => 'Expensive']);

        $response = $this->getJson('/api/products?sort=price_desc');

        $response->assertStatus(200);

        $data = $response->json('products.data');
        $this->assertEquals('Expensive', $data[0]['name']);
        $this->assertEquals('Cheap', $data[1]['name']);
    }
}
