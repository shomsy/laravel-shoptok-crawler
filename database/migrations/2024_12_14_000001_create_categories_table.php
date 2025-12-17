<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        Schema::create(table: 'categories', callback: static function (Blueprint $table): void {
            $table->id();
            $table->string(column: 'name');
            $table->string(column: 'slug')->unique();
            $table->foreignId(column: 'parent_id')->nullable()->constrained(table: 'categories');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(table: 'categories');
    }
};
