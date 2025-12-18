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

    /**
     * Test logic: Index endpoint returns 200 and paginated structure.
     */
    public function test_products_endpoint_returns_paginated_list()
    {
        Product::factory()->count(count: 25)->create();

        $response = $this->getJson(uri: '/api/products');

        $response->assertStatus(status: 200)
            ->assertJsonStructure(structure: [
                                                 'products' => [
                                                     'data' => [
                                                         '*' => ['id', 'name', 'price', 'brand', 'image_url']
                                                     ],
                                                     'links',
                                                 ],
                                                 'sidebar_tree'
                                             ]);

        // Pagination Limit Check
        $this->assertEquals(expected: 20, actual: $response->json(key: 'products.per_page'));
        $this->assertEquals(expected: 25, actual: $response->json(key: 'products.total'));
    }

    /**
     * Test logic: Brand Filtering works correctly.
     */
    public function test_filter_by_brand()
    {
        $category = Category::factory()->create();

        // Product we want
        Product::factory()->create(attributes: ['brand' => 'Samsung', 'category_id' => $category->id]);

        // Product we don't want
        Product::factory()->create(attributes: ['brand' => 'Apple', 'category_id' => $category->id]);

        // Act: Filter by Samsung
        $response = $this->getJson(uri: '/api/products?brand=Samsung');

        $response->assertStatus(status: 200);
        $this->assertEquals(expected: 1, actual: $response->json(key: 'products.total'));
        $this->assertEquals(expected: 'Samsung', actual: $response->json(key: 'products.data.0.brand'));
    }

    /**
     * Test logic: Sorting by Price ASC works.
     */
    public function test_sort_by_price_asc()
    {
        Product::factory()->create(attributes: ['price' => 100, 'name' => 'Expensive']);
        Product::factory()->create(attributes: ['price' => 10, 'name' => 'Cheap']);

        $response = $this->getJson(uri: '/api/products?sort=price_asc');

        $response->assertStatus(status: 200);

        $data = $response->json(key: 'products.data');
        $this->assertEquals(expected: 'Cheap', actual: $data[0]['name']);
        $this->assertEquals(expected: 'Expensive', actual: $data[1]['name']);
    }

    /**
     * Test logic: Sorting by Price DESC works.
     */
    public function test_sort_by_price_desc()
    {
        Product::factory()->create(attributes: ['price' => 10, 'name' => 'Cheap']);
        Product::factory()->create(attributes: ['price' => 100, 'name' => 'Expensive']);

        $response = $this->getJson(uri: '/api/products?sort=price_desc');

        $response->assertStatus(status: 200);

        $data = $response->json(key: 'products.data');
        $this->assertEquals(expected: 'Expensive', actual: $data[0]['name']);
        $this->assertEquals(expected: 'Cheap', actual: $data[1]['name']);
    }

    protected function setUp() : void
    {
        parent::setUp();
        Cache::flush(); // Ensure fresh state
    }
}
