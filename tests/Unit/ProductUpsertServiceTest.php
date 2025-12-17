<?php

namespace Tests\Unit;

use App\Services\ProductUpsertService;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Unit Tests for ProductUpsertService
 * 
 * Tests the database service responsible for upserting products.
 */
class ProductUpsertServiceTest extends TestCase
{
    use RefreshDatabase;

    private ProductUpsertService $service;
    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ProductUpsertService();
        $this->category = Category::factory()->create();
    }

    /**
     * Test: Single product upsert creates new product
     */
    public function test_upsert_creates_new_product()
    {
        $data = [
            'external_id' => 'test-123',
            'name' => 'Test Product',
            'brand' => 'TestBrand',
            'price' => 99.99,
            'currency' => 'EUR',
            'image_url' => 'https://example.com/image.jpg',
            'product_url' => 'https://example.com/product',
        ];

        $product = $this->service->upsert($data, $this->category);

        $this->assertInstanceOf(Product::class, $product);
        $this->assertEquals('test-123', $product->external_id);
        $this->assertEquals('Test Product', $product->name);
        $this->assertEquals(99.99, $product->price);
        $this->assertEquals($this->category->id, $product->category_id);
    }

    /**
     * Test: Upsert updates existing product
     */
    public function test_upsert_updates_existing_product()
    {
        // Create initial product
        $product = Product::factory()->create([
            'external_id' => 'test-456',
            'name' => 'Old Name',
            'price' => 50.00,
            'category_id' => $this->category->id,
        ]);

        // Upsert with new data
        $data = [
            'external_id' => 'test-456',
            'name' => 'Updated Name',
            'brand' => 'NewBrand',
            'price' => 75.00,
            'currency' => 'EUR',
            'image_url' => 'https://example.com/new.jpg',
            'product_url' => 'https://example.com/new',
        ];

        $updated = $this->service->upsert($data, $this->category);

        $this->assertEquals($product->id, $updated->id);
        $this->assertEquals('Updated Name', $updated->name);
        $this->assertEquals(75.00, $updated->price);
        $this->assertEquals('NewBrand', $updated->brand);
    }

    /**
     * Test: Batch upsert handles multiple products
     */
    public function test_upsert_batch_creates_multiple_products()
    {
        $items = [
            [
                'external_id' => 'batch-1',
                'name' => 'Product 1',
                'brand' => 'Brand A',
                'price' => 100.00,
                'currency' => 'EUR',
                'image_url' => 'https://example.com/1.jpg',
                'product_url' => 'https://example.com/1',
            ],
            [
                'external_id' => 'batch-2',
                'name' => 'Product 2',
                'brand' => 'Brand B',
                'price' => 200.00,
                'currency' => 'EUR',
                'image_url' => 'https://example.com/2.jpg',
                'product_url' => 'https://example.com/2',
            ],
        ];

        $affected = $this->service->upsertBatch($items, $this->category);

        $this->assertGreaterThanOrEqual(2, $affected);
        $this->assertDatabaseHas('products', ['external_id' => 'batch-1']);
        $this->assertDatabaseHas('products', ['external_id' => 'batch-2']);
    }

    /**
     * Test: Batch upsert updates existing products
     */
    public function test_upsert_batch_updates_existing_products()
    {
        // Create existing products
        Product::factory()->create([
            'external_id' => 'batch-3',
            'name' => 'Old Product',
            'price' => 50.00,
            'category_id' => $this->category->id,
        ]);

        $items = [
            [
                'external_id' => 'batch-3',
                'name' => 'Updated Product',
                'brand' => 'Updated Brand',
                'price' => 150.00,
                'currency' => 'EUR',
                'image_url' => 'https://example.com/updated.jpg',
                'product_url' => 'https://example.com/updated',
            ],
        ];

        $this->service->upsertBatch($items, $this->category);

        $this->assertDatabaseHas('products', [
            'external_id' => 'batch-3',
            'name' => 'Updated Product',
            'price' => 150.00,
        ]);
    }

    /**
     * Test: Empty batch returns zero
     */
    public function test_upsert_batch_handles_empty_array()
    {
        $affected = $this->service->upsertBatch([], $this->category);
        $this->assertEquals(0, $affected);
    }

    /**
     * Test: Upsert handles nullable brand
     */
    public function test_upsert_handles_nullable_brand()
    {
        $data = [
            'external_id' => 'test-no-brand',
            'name' => 'Product Without Brand',
            'price' => 99.99,
            'currency' => 'EUR',
            'image_url' => 'https://example.com/image.jpg',
            'product_url' => 'https://example.com/product',
        ];

        $product = $this->service->upsert($data, $this->category);

        $this->assertNull($product->brand);
    }
}
