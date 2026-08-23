<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Immutable ledger of every attempt against a payment (charge,
        // refund, chargeback, adjustment). Corrections are new rows here,
        // never edits to an existing one - no updated_at, no soft deletes.
        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['charge', 'refund', 'chargeback', 'adjustment']);
            $table->enum('status', ['pending', 'succeeded', 'failed'])->default('pending');
            $table->decimal('amount', 12, 2);
            $table->string('gateway_transaction_id')->nullable()->unique();
            // Sanitized gateway metadata only - never raw card data
            // (CLAUDE.md §5).
            $table->json('raw_response')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('payment_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_transactions');
    }
};
