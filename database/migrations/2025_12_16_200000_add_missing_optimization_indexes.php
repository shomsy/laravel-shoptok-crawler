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
        Schema::table('categories', function (Blueprint $table) {
            // Index for parent_id to speed up children queries (Smart Sidebar)
            $table->index('parent_id', 'idx_parent_category');
        });

        Schema::table('products', function (Blueprint $table) {
            // Index for brand filtering (standalone)
            $table->index('brand', 'idx_brand');

            // Foreign Key Index for category_id (Critical for JOINs)
            // If the FK constraint already exists, this might be redundant but safe if not.
            // Using a plain index here to ensure performance regardless of constraint existence.
            $table->index('category_id', 'idx_category_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropIndex('idx_parent_category');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('idx_brand');
            $table->dropIndex('idx_category_id');
        });
    }
};
