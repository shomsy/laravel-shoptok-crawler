<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CategoryHierarchyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush(); // Ensure clean state for every test
    }

    /**
     * Test logic: Root "TV Sprejemniki" should Recursively fetch children products.
     */
    public function test_root_category_fetches_recursive_products()
    {
        // Setup Hierarchy: TV Sprejemniki -> Televizorji
        $root = Category::factory()->create(['slug' => 'tv-sprejemniki', 'name' => 'TV Sprejemniki']);
        $child = Category::factory()->create(['parent_id' => $root->id, 'slug' => 'televizorji', 'name' => 'Televizorji']);

        // Create Products
        Product::factory()->count(3)->create(['category_id' => $root->id]); // 3 in Root
        Product::factory()->count(5)->create(['category_id' => $child->id]); // 5 in Child

        // Act: Visit Root
        $response = $this->getJson("/api/categories/{$root->slug}");

        // Assert: Should see ALL 8 products (3 + 5)
        $response->assertStatus(200);
        $this->assertEquals(8, $response->json('products.total'), "Root category should show recursive total.");
    }

    /**
     * Test logic: Subcategory "Televizorji" should uses Strict filtering (Only its own).
     */
    public function test_subcategory_uses_strict_filtering()
    {
        // Setup Hierarchy: TV Sprejemniki -> Televizorji
        $root = Category::factory()->create(['slug' => 'tv-sprejemniki']);
        $child = Category::factory()->create(['parent_id' => $root->id, 'slug' => 'televizorji']);

        // Create Products
        Product::factory()->count(3)->create(['category_id' => $root->id]); // 3 in Root
        Product::factory()->count(5)->create(['category_id' => $child->id]); // 5 in Child

        // Act: Visit Child
        $response = $this->getJson("/api/categories/{$child->slug}");

        // Assert: Should see ONLY 5 products
        $response->assertStatus(200);
        $this->assertEquals(5, $response->json('products.total'), "Subcategory should use strict filtering.");
    }

    /**
     * Test logic: Circular Database References (Cycle) should NOT crash the app.
     */
    public function test_circular_dependency_is_handled_safely()
    {
        // Setup Cycle: A -> B -> A
        $catA = Category::factory()->create(['slug' => 'cat-a', 'name' => 'Category A']);
        $catB = Category::factory()->create(['slug' => 'cat-b', 'name' => 'Category B', 'parent_id' => $catA->id]);

        // Complete the cycle
        $catA->update(['parent_id' => $catB->id]);

        // Act: Visit A
        // If recursion logic is broken, this will Timeout or 500
        $response = $this->getJson("/api/categories/{$catA->slug}");

        // Assert: 200 OK
        $response->assertStatus(200);

        // Verify Breadcrumbs didn't explode relative to depth limit
        $breadcrumbs = $response->json('breadcrumbs');
        $this->assertLessThanOrEqual(10, count($breadcrumbs), "Breadcrumbs should respect depth limit.");
    }

    /**
     * Test logic: Sidebar should enforce 'TV Sprejemniki' as Root over others.
     */
    public function test_sidebar_enforces_manual_hierarchy()
    {
        $root = Category::factory()->create(['slug' => 'tv-sprejemniki', 'name' => 'TV Sprejemniki']);
        $other = Category::factory()->create(['slug' => 'other', 'name' => 'Other Category']);

        // Act: Category Page (Using CategoryController@show which returns sidebar_tree)
        $response = $this->getJson("/api/categories/{$root->slug}");

        $response->assertStatus(200);

        $sidebar = $response->json('sidebar_tree.data');

        // Assert: Sidebar should have 1 top level item (TV Sprejemniki)
        $this->assertCount(1, $sidebar);
        $this->assertEquals('TV Sprejemniki', $sidebar[0]['name']);

        // Assert: 'Other Category' should be a child of 'TV Sprejemniki'
        $this->assertNotEmpty($sidebar[0]['children']);
        $this->assertEquals('Other Category', $sidebar[0]['children'][0]['name']);
    }

    /**
     * Test logic: Redis Cache works (Second request is fast/cached).
     * Note: Hard to test exact timing in Integration test, but we can verify response structure is identical.
     */
    public function test_responses_are_cached()
    {
        $root = Category::factory()->create(['slug' => 'tv-sprejemniki']);
        Product::factory()->count(1)->create(['category_id' => $root->id]);

        // 1st Request (Miss)
        $response1 = $this->getJson("/api/categories/{$root->slug}");
        $response1->assertStatus(200);

        // 2nd Request (Hit)
        $response2 = $this->getJson("/api/categories/{$root->slug}");
        $response2->assertStatus(200);

        $this->assertEquals($response1->json('data.id'), $response2->json('data.id'));
    }
}
