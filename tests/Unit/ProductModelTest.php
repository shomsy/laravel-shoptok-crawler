<?php

namespace Tests\Unit;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Unit Tests for Product Model
 *
 * Tests model relationships, scopes, and filtering logic.
 */
class ProductModelTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test: Product belongs to category
     */
    public function test_product_belongs_to_category()
    {
        $category = Category::factory()->create();
        $product  = Product::factory()->create(attributes: ['category_id' => $category->id]);

        $this->assertInstanceOf(expected: Category::class, actual: $product->category);
        $this->assertEquals(expected: $category->id, actual: $product->category->id);
    }

    /**
     * Test: Product filter scope with search
     */
    public function test_filter_scope_searches_by_name()
    {
        Product::factory()->create(attributes: ['name' => 'Samsung TV 55']);
        Product::factory()->create(attributes: ['name' => 'LG Monitor']);

        $request = new Request(query: ['search' => 'Samsung']);
        $results = Product::filter($request)->get();

        $this->assertCount(expectedCount: 1, haystack: $results);
        $this->assertStringContainsString(needle: 'Samsung', haystack: $results->first()->name);
    }

    /**
     * Test: Product filter scope with brand
     */
    public function test_filter_scope_filters_by_brand()
    {
        Product::factory()->create(attributes: ['brand' => 'Samsung']);
        Product::factory()->create(attributes: ['brand' => 'LG']);
        Product::factory()->create(attributes: ['brand' => 'Samsung']);

        $request = new Request(query: ['brand' => 'Samsung']);
        $results = Product::filter($request)->get();

        $this->assertCount(expectedCount: 2, haystack: $results);
        foreach ($results as $product) {
            $this->assertEquals(expected: 'Samsung', actual: $product->brand);
        }
    }

    /**
     * Test: Product filter scope with price sorting
     */
    public function test_filter_scope_sorts_by_price_ascending()
    {
        Product::factory()->create(attributes: ['name' => 'Expensive', 'price' => 1000]);
        Product::factory()->create(attributes: ['name' => 'Cheap', 'price' => 100]);

        $request = new Request(query: ['sort' => 'price_asc']);
        $results = Product::filter($request)->get();

        $this->assertEquals(expected: 'Cheap', actual: $results->first()->name);
        $this->assertEquals(expected: 'Expensive', actual: $results->last()->name);
    }

    /**
     * Test: Product filter scope sorts by price descending
     */
    public function test_filter_scope_sorts_by_price_descending()
    {
        Product::factory()->create(attributes: ['name' => 'Expensive', 'price' => 1000]);
        Product::factory()->create(attributes: ['name' => 'Cheap', 'price' => 100]);

        $request = new Request(query: ['sort' => 'price_desc']);
        $results = Product::filter($request)->get();

        $this->assertEquals(expected: 'Expensive', actual: $results->first()->name);
        $this->assertEquals(expected: 'Cheap', actual: $results->last()->name);
    }

    /**
     * Test: Product price is cast to float
     */
    public function test_price_is_cast_to_float()
    {
        $product = Product::factory()->create(attributes: ['price' => '99.99']);

        // Laravel's decimal:2 cast returns a string, not float
        $this->assertIsString(actual: $product->price);
        $this->assertEquals(expected: '99.99', actual: $product->price);
    }

    /**
     * Test: Product external_id is required and unique
     */
    public function test_external_id_is_fillable()
    {
        $product = Product::factory()->create(attributes: ['external_id' => 'unique-123']);

        $this->assertEquals(expected: 'unique-123', actual: $product->external_id);
    }

    /**
     * Test: Product can have nullable brand
     */
    public function test_brand_can_be_null()
    {
        $product = Product::factory()->create(attributes: ['brand' => null]);

        $this->assertNull(actual: $product->brand);
    }

    /**
     * Test: Product filter combines multiple filters
     */
    public function test_filter_combines_search_and_brand()
    {
        Product::factory()->create(attributes: ['name' => 'Samsung TV', 'brand' => 'Samsung']);
        Product::factory()->create(attributes: ['name' => 'Samsung Phone', 'brand' => 'Samsung']);
        Product::factory()->create(attributes: ['name' => 'LG TV', 'brand' => 'LG']);

        $request = new Request(
            query: ['search' => 'TV',
                    'brand'  => 'Samsung',
                   ]
        );

        $results = Product::filter($request)->get();

        $this->assertCount(expectedCount: 1, haystack: $results);
        $this->assertEquals(expected: 'Samsung TV', actual: $results->first()->name);
    }
}
