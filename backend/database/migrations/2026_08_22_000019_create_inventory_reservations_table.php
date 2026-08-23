<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_id')->constrained('product_variants')->cascadeOnDelete();
            // Nullable so a reservation can be held during checkout before
            // an order row exists yet (e.g. a payment-pending cart hold).
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->integer('quantity');
            $table->enum('status', ['pending', 'committed', 'released', 'expired'])->default('pending');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['product_variant_id', 'status']);
            $table->index('order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_reservations');
    }
};
