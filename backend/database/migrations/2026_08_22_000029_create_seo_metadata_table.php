<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Polymorphic: one row can describe SEO metadata for a product, a
        // category, a homepage section, or any future seoable entity
        // without a metadata table per model.
        Schema::create('seo_metadata', function (Blueprint $table) {
            $table->id();
            $table->string('seoable_type');
            $table->unsignedBigInteger('seoable_id');
            $table->string('meta_title')->nullable();
            $table->string('meta_description')->nullable();
            $table->string('meta_keywords')->nullable();
            $table->string('canonical_url')->nullable();
            $table->string('og_image_url')->nullable();
            $table->timestamps();

            $table->unique(['seoable_type', 'seoable_id'], 'ux_seo_metadata_seoable');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_metadata');
    }
};
