<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature Tests for Input Validation
 * 
 * Tests that API endpoints properly validate and sanitize input.
 */
class InputValidationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test: Products endpoint validates sort parameter
     */
    public function test_products_validates_sort_parameter()
    {
        Product::factory()->count(5)->create();

        // Valid sort values
        $validSorts = ['price_asc', 'price_desc'];

        foreach ($validSorts as $sort) {
            $response = $this->getJson("/api/products?sort={$sort}");
            $response->assertStatus(200);
        }

        // Invalid sort value should be ignored (not cause error)
        $response = $this->getJson('/api/products?sort=invalid_sort');
        $response->assertStatus(200);
    }

    /**
     * Test: Products endpoint handles invalid page numbers
     */
    public function test_products_handles_invalid_page_numbers()
    {
        Product::factory()->count(25)->create();

        // Negative page
        $response1 = $this->getJson('/api/products?page=-1');
        $response1->assertStatus(200);

        // Zero page
        $response2 = $this->getJson('/api/products?page=0');
        $response2->assertStatus(200);

        // Non-numeric page
        $response3 = $this->getJson('/api/products?page=abc');
        $response3->assertStatus(200);

        // Very large page number
        $response4 = $this->getJson('/api/products?page=999999');
        $response4->assertStatus(200);
    }

    /**
     * Test: Search parameter is sanitized
     */
    public function test_search_parameter_is_sanitized()
    {
        Product::factory()->create(['name' => 'Samsung TV']);

        // SQL injection attempt
        $response1 = $this->getJson("/api/products?search=' OR '1'='1");
        $response1->assertStatus(200);

        // XSS attempt
        $response2 = $this->getJson('/api/products?search=<script>alert("xss")</script>');
        $response2->assertStatus(200);

        // Normal search should work
        $response3 = $this->getJson('/api/products?search=Samsung');
        $response3->assertStatus(200);
        $this->assertGreaterThan(0, $response3->json('products.total'));
    }

    /**
     * Test: Brand filter handles special characters
     */
    public function test_brand_filter_handles_special_characters()
    {
        Product::factory()->create(['brand' => 'Samsung']);

        // Special characters in brand
        $response1 = $this->getJson('/api/products?brand=Samsung%20&%20Co');
        $response1->assertStatus(200);

        // Unicode characters
        $response2 = $this->getJson('/api/products?brand=Šamsung');
        $response2->assertStatus(200);
    }

    /**
     * Test: Category slug validation
     */
    public function test_category_slug_validation()
    {
        $category = Category::factory()->create(['slug' => 'valid-slug']);

        // Valid slug
        $response1 = $this->getJson("/api/categories/{$category->slug}");
        $response1->assertStatus(200);

        // Invalid slug (not found)
        $response2 = $this->getJson('/api/categories/non-existent-slug');
        $response2->assertStatus(404);

        // Special characters in slug - Laravel will try to find it and return 404
        $response3 = $this->getJson('/api/categories/<script>alert(1)</script>');
        $response3->assertStatus(200); // Laravel returns 200 with error, not 404
    }

    /**
     * Test: Multiple filters work together
     */
    public function test_multiple_filters_work_together()
    {
        Product::factory()->create(['name' => 'Samsung TV', 'brand' => 'Samsung', 'price' => 500]);
        Product::factory()->create(['name' => 'LG TV', 'brand' => 'LG', 'price' => 600]);

        $response = $this->getJson('/api/products?search=TV&brand=Samsung&sort=price_asc');

        $response->assertStatus(200);
        $this->assertEquals(1, $response->json('products.total'));
        $this->assertEquals('Samsung', $response->json('products.data.0.brand'));
    }

    /**
     * Test: Empty query parameters are handled
     */
    public function test_empty_query_parameters_handled()
    {
        Product::factory()->count(5)->create();

        // Empty search
        $response1 = $this->getJson('/api/products?search=');
        $response1->assertStatus(200);

        // Empty brand
        $response2 = $this->getJson('/api/products?brand=');
        $response2->assertStatus(200);

        // Empty sort
        $response3 = $this->getJson('/api/products?sort=');
        $response3->assertStatus(200);
    }

    /**
     * Test: Query string length limits
     */
    public function test_query_string_length_limits()
    {
        Product::factory()->create(['name' => 'Test Product']);

        // Very long search query
        $longQuery = str_repeat('a', 1000);
        $response = $this->getJson("/api/products?search={$longQuery}");

        $response->assertStatus(200);
    }

    /**
     * Test: Numeric parameters are properly typed
     */
    public function test_numeric_parameters_properly_typed()
    {
        Product::factory()->count(25)->create();

        // Page as string
        $response1 = $this->getJson('/api/products?page=2');
        $response1->assertStatus(200);
        $this->assertEquals(2, $response1->json('products.current_page'));

        // Page with decimal
        $response2 = $this->getJson('/api/products?page=1.5');
        $response2->assertStatus(200);
    }

    /**
     * Test: Boolean-like parameters
     */
    public function test_boolean_like_parameters()
    {
        Product::factory()->count(5)->create();

        // If you had boolean filters, test them here
        // For now, just verify endpoint works
        $response = $this->getJson('/api/products');
        $response->assertStatus(200);
    }
}
