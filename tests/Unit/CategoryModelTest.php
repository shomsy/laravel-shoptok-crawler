<?php

namespace Tests\Unit;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Unit Tests for Category Model
 *
 * Tests model relationships, scopes, and business logic.
 */
class CategoryModelTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test: Category has products relationship
     */
    public function test_category_has_products_relationship()
    {
        $category = Category::factory()->create();
        Product::factory()->count(count: 3)->create(attributes: ['category_id' => $category->id]);

        $this->assertCount(expectedCount: 3, haystack: $category->products);
        $this->assertInstanceOf(expected: Product::class, actual: $category->products->first());
    }

    /**
     * Test: Category has parent relationship
     */
    public function test_category_has_parent_relationship()
    {
        $parent = Category::factory()->create(attributes: ['parent_id' => null]);
        $child  = Category::factory()->create(attributes: ['parent_id' => $parent->id]);

        $this->assertNotNull(actual: $child->parent);
        $this->assertEquals(expected: $parent->id, actual: $child->parent->id);
    }

    /**
     * Test: Category has children relationship
     */
    public function test_category_has_children_relationship()
    {
        $parent = Category::factory()->create(attributes: ['parent_id' => null]);
        Category::factory()->count(count: 2)->create(attributes: ['parent_id' => $parent->id]);

        $this->assertCount(expectedCount: 2, haystack: $parent->children);
    }

    /**
     * Test: Root categories scope
     */
    public function test_roots_scope_returns_only_root_categories()
    {
        $root1 = Category::factory()->create(attributes: ['parent_id' => null]);
        $root2 = Category::factory()->create(attributes: ['parent_id' => null]);
        Category::factory()->create(attributes: ['parent_id' => $root1->id]); // Child

        $roots = Category::roots()->get();

        $this->assertCount(expectedCount: 2, haystack: $roots);
        $this->assertTrue(condition: $roots->contains(key: $root1));
        $this->assertTrue(condition: $roots->contains(key: $root2));
    }

    /**
     * Test: Category can have null parent (root category)
     */
    public function test_category_can_be_root()
    {
        $category = Category::factory()->create(attributes: ['parent_id' => null]);

        $this->assertNull(actual: $category->parent_id);
        $this->assertNull(actual: $category->parent);
    }

    /**
     * Test: Category slug is unique
     */
    public function test_category_slug_is_fillable()
    {
        $category = Category::create([
                                         'name' => 'Test Category',
                                         'slug' => 'test-category',
                                     ]);

        $this->assertEquals(expected: 'test-category', actual: $category->slug);
    }

    /**
     * Test: Category can have multiple levels of nesting
     */
    public function test_category_supports_deep_nesting()
    {
        $level1 = Category::factory()->create(attributes: ['parent_id' => null]);
        $level2 = Category::factory()->create(attributes: ['parent_id' => $level1->id]);
        $level3 = Category::factory()->create(attributes: ['parent_id' => $level2->id]);

        $this->assertEquals(expected: $level1->id, actual: $level2->parent_id);
        $this->assertEquals(expected: $level2->id, actual: $level3->parent_id);
        $this->assertNull(actual: $level1->parent_id);
    }
}
