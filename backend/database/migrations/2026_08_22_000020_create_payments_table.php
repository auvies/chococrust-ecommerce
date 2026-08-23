<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            // An order can have more than one payment row over its life
            // (e.g. a failed attempt followed by a successful retry).
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();

            // Not an enum: the gateway lineup (COD, card, JazzCash,
            // EasyPaisa, ...) is still a business decision (see README's
            // Payment Methods TBD) and shouldn't require a migration to add.
            $table->string('method')->index();

            // Payment status only. Order fulfillment status lives entirely
            // on orders.status - this column never conflates the two.
            $table->enum('status', [
                'pending', 'authorized', 'paid', 'partially_refunded',
                'refunded', 'failed', 'cancelled',
            ])->default('pending')->index();

            $table->decimal('amount', 12, 2);
            $table->char('currency', 3)->default('PKR');
            $table->string('gateway')->nullable();
            // CLAUDE.md §5: only the gateway's own reference/token is ever
            // stored here - never raw card numbers, CVVs, or credentials.
            $table->string('gateway_reference')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['gateway', 'gateway_reference'], 'ux_payments_gateway_reference');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
