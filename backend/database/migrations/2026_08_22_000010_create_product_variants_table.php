<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('sku')->unique();
            // e.g. "500g Box", "Dark Chocolate" - every product has at
            // least one variant, even a simple product gets a default one.
            $table->string('name');
            $table->decimal('price', 10, 2);
            $table->decimal('compare_at_price', 10, 2)->nullable();
            $table->decimal('cost_price', 10, 2)->nullable();
            $table->char('currency', 3)->default('PKR');
            $table->unsignedInteger('weight_grams')->nullable();
            $table->string('barcode')->nullable()->unique();
            // Flexible option values (size/flavor/color/...) without a
            // separate attribute-table system this catalog doesn't need yet.
            $table->json('attributes')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
