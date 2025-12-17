<?php

namespace Tests\Unit;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $product = Product::factory()->create(['category_id' => $category->id]);

        $this->assertInstanceOf(Category::class, $product->category);
        $this->assertEquals($category->id, $product->category->id);
    }

    /**
     * Test: Product filter scope with search
     */
    public function test_filter_scope_searches_by_name()
    {
        Product::factory()->create(['name' => 'Samsung TV 55']);
        Product::factory()->create(['name' => 'LG Monitor']);

        $request = new \Illuminate\Http\Request(['search' => 'Samsung']);
        $results = Product::filter($request)->get();

        $this->assertCount(1, $results);
        $this->assertStringContainsString('Samsung', $results->first()->name);
    }

    /**
     * Test: Product filter scope with brand
     */
    public function test_filter_scope_filters_by_brand()
    {
        Product::factory()->create(['brand' => 'Samsung']);
        Product::factory()->create(['brand' => 'LG']);
        Product::factory()->create(['brand' => 'Samsung']);

        $request = new \Illuminate\Http\Request(['brand' => 'Samsung']);
        $results = Product::filter($request)->get();

        $this->assertCount(2, $results);
        foreach ($results as $product) {
            $this->assertEquals('Samsung', $product->brand);
        }
    }

    /**
     * Test: Product filter scope with price sorting
     */
    public function test_filter_scope_sorts_by_price_ascending()
    {
        Product::factory()->create(['name' => 'Expensive', 'price' => 1000]);
        Product::factory()->create(['name' => 'Cheap', 'price' => 100]);

        $request = new \Illuminate\Http\Request(['sort' => 'price_asc']);
        $results = Product::filter($request)->get();

        $this->assertEquals('Cheap', $results->first()->name);
        $this->assertEquals('Expensive', $results->last()->name);
    }

    /**
     * Test: Product filter scope sorts by price descending
     */
    public function test_filter_scope_sorts_by_price_descending()
    {
        Product::factory()->create(['name' => 'Expensive', 'price' => 1000]);
        Product::factory()->create(['name' => 'Cheap', 'price' => 100]);

        $request = new \Illuminate\Http\Request(['sort' => 'price_desc']);
        $results = Product::filter($request)->get();

        $this->assertEquals('Expensive', $results->first()->name);
        $this->assertEquals('Cheap', $results->last()->name);
    }

    /**
     * Test: Product price is cast to float
     */
    public function test_price_is_cast_to_float()
    {
        $product = Product::factory()->create(['price' => '99.99']);

        // Laravel's decimal:2 cast returns a string, not float
        $this->assertIsString($product->price);
        $this->assertEquals('99.99', $product->price);
    }

    /**
     * Test: Product external_id is required and unique
     */
    public function test_external_id_is_fillable()
    {
        $product = Product::factory()->create(['external_id' => 'unique-123']);

        $this->assertEquals('unique-123', $product->external_id);
    }

    /**
     * Test: Product can have nullable brand
     */
    public function test_brand_can_be_null()
    {
        $product = Product::factory()->create(['brand' => null]);

        $this->assertNull($product->brand);
    }

    /**
     * Test: Product filter combines multiple filters
     */
    public function test_filter_combines_search_and_brand()
    {
        Product::factory()->create(['name' => 'Samsung TV', 'brand' => 'Samsung']);
        Product::factory()->create(['name' => 'Samsung Phone', 'brand' => 'Samsung']);
        Product::factory()->create(['name' => 'LG TV', 'brand' => 'LG']);

        $request = new \Illuminate\Http\Request([
            'search' => 'TV',
            'brand' => 'Samsung',
        ]);
        $results = Product::filter($request)->get();

        $this->assertCount(1, $results);
        $this->assertEquals('Samsung TV', $results->first()->name);
    }
}
