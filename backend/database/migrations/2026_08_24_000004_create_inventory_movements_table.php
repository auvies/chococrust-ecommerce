<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only inventory history, separate from the generic `audit_logs`
 * table (which mixes every admin action across every module) - this is a
 * dedicated, queryable ledger of every stock movement for a variant:
 * reservation created/committed/released/expired, a sale fulfilled
 * (permanently decrementing on-hand stock), or a manual adjustment/restock.
 * Same append-only shape as `delivery_tracking_events`/`order_status_histories`
 * - no updated_at, rows are never edited once recorded.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_id')->constrained('product_variants')->cascadeOnDelete();
            // Not an enum: this list is expected to grow (e.g. a future
            // "damaged"/"stock_count_correction" type) without a migration.
            $table->string('type');
            // Signed: negative for anything that reduces available/on-hand
            // stock (reservation, sale, shrinkage), positive for anything
            // that increases it (release, restock).
            $table->integer('quantity_delta');
            $table->foreignId('inventory_reservation_id')->nullable()->constrained('inventory_reservations')->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->string('reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['product_variant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
    }
};
