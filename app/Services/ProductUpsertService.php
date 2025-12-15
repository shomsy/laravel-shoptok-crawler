<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * 💾 **The Archivist (Database Service)**
 *
 * This service is the *only* class in the system allowed to **write**
 * product data to the database. It serves as the single, consistent
 * gateway for inserting or updating product records.
 *
 * 🧠 **Analogy:**
 * Think of it as the librarian who files each product carefully:
 * - If the product already exists → it updates its details.
 * - If it’s new → it adds it neatly to the catalog.
 *
 * 🧩 **Why this exists**
 * - Centralizes all write operations (no scattered DB access).
 * - Guarantees idempotency: running the same crawl twice won’t duplicate data.
 * - Keeps database consistency (each crawl refreshes product info safely).
 *
 * ✅ **Design Goals**
 * - Idempotent writes via `updateOrCreate` / `upsert`.
 * - Atomic DB operations.
 * - Clear exception handling and error wrapping.
 *
 * 💡 **Usage Example**
 * ```php
 * $service->upsert($data, $category);
 * $service->upsertBatch($products, $category);
 * ```
 *
 * @package App\Services
 */
final class ProductUpsertService
{
    /**
     * 📦 **Batch Upsert Multiple Products**
     *
     * Efficiently inserts or updates multiple product records in one query.
     * Great for bulk crawls — drastically reduces query overhead.
     *
     * ⚙️ **How it works:**
     * - Prepares an array of product data.
     * - Uses the `upsert()` method (DB-level batch insert/update).
     * - Ensures each row has timestamps for consistency.
     *
     * ⚠️ **Performance Note**
     * If you expect *very large* batches (e.g., >5000 records),
     * consider chunking your data into smaller groups to prevent
     * memory or query-size issues.
     *
     * @param array<int, array<string, mixed>> $items
     *        List of product data arrays parsed from HTML.
     * @param Category                         $category
     *        The category these products belong to.
     *
     * @return int
     *         Number of affected rows in the database.
     */
    public function upsertBatch(array $items, Category $category) : int
    {
        if (empty($items)) {
            return 0;
        }

        $now        = now();
        $upsertData = [];

        // 🧮 Build dataset for DB batch upsert
        foreach ($items as $item) {
            $upsertData[] = [
                'external_id' => $item['external_id'],
                'name'        => $item['name'],
                'price'       => $item['price'],
                'currency'    => $item['currency'] ?? 'EUR',
                'image_url'   => $item['image_url'],
                'product_url' => $item['product_url'],
                'category_id' => $category->id,
                'created_at'  => $now,
                'updated_at'  => $now,
            ];
        }

        // ⚙️ Perform chunked upserts for massive imports
        // Keeps queries lightweight and memory safe
        $totalAffected = 0;
        foreach (array_chunk(array: $upsertData, length: 1000) as $chunk) {
            $totalAffected += Product::upsert(
                values  : $chunk,
                uniqueBy: ['external_id'],
                update  : [
                              'name',
                              'price',
                              'currency',
                              'image_url',
                              'product_url',
                              'category_id',
                              'updated_at'
                          ]
            );
        }

        return $totalAffected;
    }

    /**
     * 🧱 **Insert or Update a Single Product**
     *
     * Performs an idempotent write based on `external_id`.
     * This means:
     * - If a product already exists, we update its record.
     * - If it does not exist, we create a new one.
     *
     * ⚙️ **When to use:**
     * Use this when saving individual products (e.g. within a loop or
     * when scraping a single detail page).
     *
     * @param array<string, mixed> $data
     *        Parsed product data array (from parser service).
     * @param Category             $category
     *        The category this product belongs to.
     *
     * @return Product
     *         The updated or newly created Eloquent model.
     *
     * @throws RuntimeException
     *         If a database error occurs (wrapped from QueryException).
     */
    public function upsert(array $data, Category $category) : Product
    {
        try {
            // 🚀 Atomic “updateOrCreate” ensures idempotent writes.
            // If the record exists → update. Else → insert.
            return Product::updateOrCreate(
                attributes: ['external_id' => $data['external_id']],
                values    : [
                                'name'        => $data['name'],
                                'price'       => $data['price'],
                                'currency'    => $data['currency'] ?? 'EUR',
                                'image_url'   => $data['image_url'],
                                'product_url' => $data['product_url'],
                                'category_id' => $category->id,
                            ]
            );
        } catch (QueryException|Throwable $e) {
            // 🧯 Defensive error handling — catch DB-level issues cleanly.
            Log::error(message: 'Product upsert failed', context: [
                'external_id' => $data['external_id'] ?? null,
                'name'        => $data['name'] ?? 'unknown',
                'error'       => $e->getMessage(),
            ]);

            throw new RuntimeException(
                message : sprintf('Failed to upsert product "%s"', $data['name'] ?? 'unknown'),
                code    : 0,
                previous: $e
            );
        }
    }
}
