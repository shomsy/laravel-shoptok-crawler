<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        Schema::create(table: 'products', callback: static function (Blueprint $table): void {
            $table->id();
            $table->string(column: 'external_id')->unique();
            $table->string(column: 'name');
            $table->decimal(column: 'price', total: 12, places: 2)->default(0);
            $table->string(column: 'currency', length: 5)->default('EUR');
            $table->text('image_url')->nullable();
            $table->text('product_url');
            $table->foreignId(column: 'category_id')->constrained(table: 'categories');
            $table->timestamps();

            $table->index(columns: ['category_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(table: 'products');
    }
};
