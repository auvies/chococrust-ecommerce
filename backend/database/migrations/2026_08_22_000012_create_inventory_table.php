<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_id')->constrained('product_variants')->cascadeOnDelete();
            // Defaults to a single 'main' location today; the column exists
            // so multi-warehouse support doesn't require a schema redesign.
            $table->string('location')->default('main');
            $table->integer('quantity_on_hand')->default(0);
            // No quantity_reserved column here on purpose: inventory_reservations
            // is the single source of truth for reserved stock. Available =
            // quantity_on_hand - SUM(active reservations), computed at query
            // time so the two numbers can never drift apart.
            $table->integer('reorder_level')->nullable();
            $table->timestamps();

            $table->unique(['product_variant_id', 'location'], 'ux_inventory_variant_location');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory');
    }
};
