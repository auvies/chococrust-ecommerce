<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // COD gets its own controlled state machine, separate from both
        // orders.status (fulfillment) and payments.status (generic payment
        // lifecycle). This table is the only place COD-specific operational
        // state - collection, deposit, failure - is tracked.
        Schema::create('cod_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('payment_id')->unique()->constrained()->cascadeOnDelete();
            $table->enum('status', [
                'awaiting_delivery', 'collected', 'deposited', 'failed_collection', 'returned',
            ])->default('awaiting_delivery');
            $table->decimal('amount_due', 12, 2);
            $table->decimal('amount_collected', 12, 2)->nullable();
            $table->foreignId('collected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('collected_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cod_records');
    }
};
