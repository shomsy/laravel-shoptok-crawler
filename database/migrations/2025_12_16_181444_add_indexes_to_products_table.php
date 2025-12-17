<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table(table: 'products', callback: function (Blueprint $table) {
            // Covering Index for filtering + sorting by price
            // This allows MySQL to satisfy the entire query from the index tree.
            $table->index(columns: ['category_id', 'brand', 'price'], name: 'idx_category_brand_price');

            // Standard price index for global sorts
            $table->index(columns: 'price');
        });

        Schema::table(table: 'categories', callback: function (Blueprint $table) {
            // Ensure slug is indexed for fast lookups (if not already unique)
            // Typically unique() creates an index, but explicit index is safe.
            $table->index(columns: 'slug');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table(table: 'products', callback: function (Blueprint $table) {
            $table->dropIndex(index: 'idx_category_brand_price');
            $table->dropIndex(index: ['price']);
        });

        Schema::table(table: 'categories', callback: function (Blueprint $table) {
            $table->dropIndex(index: ['slug']);
        });
    }
};
