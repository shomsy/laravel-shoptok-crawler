<?php

namespace Tests\Unit;

use App\Models\Category;
use App\Models\Product;
use App\Services\ProductUpsertService;
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
    private Category             $category;

    /**
     * Test: Single product upsert creates new product
     */
    public function test_upsert_creates_new_product()
    {
        $data = [
            'external_id' => 'test-123',
            'name'        => 'Test Product',
            'brand'       => 'TestBrand',
            'price'       => 99.99,
            'currency'    => 'EUR',
            'image_url'   => 'https://example.com/image.jpg',
            'product_url' => 'https://example.com/product',
        ];

        $product = $this->service->upsert(data: $data, category: $this->category);

        $this->assertInstanceOf(expected: Product::class, actual: $product);
        $this->assertEquals(expected: 'test-123', actual: $product->external_id);
        $this->assertEquals(expected: 'Test Product', actual: $product->name);
        $this->assertEquals(expected: 99.99, actual: $product->price);
        $this->assertEquals(expected: $this->category->id, actual: $product->category_id);
    }

    /**
     * Test: Upsert updates existing product
     */
    public function test_upsert_updates_existing_product()
    {
        // Create initial product
        $product = Product::factory()->create(attributes: [
                                                              'external_id' => 'test-456',
                                                              'name'        => 'Old Name',
                                                              'price'       => 50.00,
                                                              'category_id' => $this->category->id,
                                                          ]);

        // Upsert with new data
        $data = [
            'external_id' => 'test-456',
            'name'        => 'Updated Name',
            'brand'       => 'NewBrand',
            'price'       => 75.00,
            'currency'    => 'EUR',
            'image_url'   => 'https://example.com/new.jpg',
            'product_url' => 'https://example.com/new',
        ];

        $updated = $this->service->upsert(data: $data, category: $this->category);

        $this->assertEquals(expected: $product->id, actual: $updated->id);
        $this->assertEquals(expected: 'Updated Name', actual: $updated->name);
        $this->assertEquals(expected: 75.00, actual: $updated->price);
        $this->assertEquals(expected: 'NewBrand', actual: $updated->brand);
    }

    /**
     * Test: Batch upsert handles multiple products
     */
    public function test_upsert_batch_creates_multiple_products()
    {
        $items = [
            [
                'external_id' => 'batch-1',
                'name'        => 'Product 1',
                'brand'       => 'Brand A',
                'price'       => 100.00,
                'currency'    => 'EUR',
                'image_url'   => 'https://example.com/1.jpg',
                'product_url' => 'https://example.com/1',
            ],
            [
                'external_id' => 'batch-2',
                'name'        => 'Product 2',
                'brand'       => 'Brand B',
                'price'       => 200.00,
                'currency'    => 'EUR',
                'image_url'   => 'https://example.com/2.jpg',
                'product_url' => 'https://example.com/2',
            ],
        ];

        $affected = $this->service->upsertBatch(items: $items, category: $this->category);

        $this->assertGreaterThanOrEqual(minimum: 2, actual: $affected);
        $this->assertDatabaseHas(table: 'products', data: ['external_id' => 'batch-1']);
        $this->assertDatabaseHas(table: 'products', data: ['external_id' => 'batch-2']);
    }

    /**
     * Test: Batch upsert updates existing products
     */
    public function test_upsert_batch_updates_existing_products()
    {
        // Create existing products
        Product::factory()->create(attributes: [
                                                   'external_id' => 'batch-3',
                                                   'name'        => 'Old Product',
                                                   'price'       => 50.00,
                                                   'category_id' => $this->category->id,
                                               ]);

        $items = [
            [
                'external_id' => 'batch-3',
                'name'        => 'Updated Product',
                'brand'       => 'Updated Brand',
                'price'       => 150.00,
                'currency'    => 'EUR',
                'image_url'   => 'https://example.com/updated.jpg',
                'product_url' => 'https://example.com/updated',
            ],
        ];

        $this->service->upsertBatch(items: $items, category: $this->category);

        $this->assertDatabaseHas(table: 'products', data: [
            'external_id' => 'batch-3',
            'name'        => 'Updated Product',
            'price'       => 150.00,
        ]);
    }

    /**
     * Test: Empty batch returns zero
     */
    public function test_upsert_batch_handles_empty_array()
    {
        $affected = $this->service->upsertBatch(items: [], category: $this->category);
        $this->assertEquals(expected: 0, actual: $affected);
    }

    /**
     * Test: Upsert handles nullable brand
     */
    public function test_upsert_handles_nullable_brand()
    {
        $data = [
            'external_id' => 'test-no-brand',
            'name'        => 'Product Without Brand',
            'price'       => 99.99,
            'currency'    => 'EUR',
            'image_url'   => 'https://example.com/image.jpg',
            'product_url' => 'https://example.com/product',
        ];

        $product = $this->service->upsert(data: $data, category: $this->category);

        $this->assertNull(actual: $product->brand);
    }

    protected function setUp() : void
    {
        parent::setUp();
        $this->service  = new ProductUpsertService();
        $this->category = Category::factory()->create();
    }
}
