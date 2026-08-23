<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The event architecture's durable feed (ORDER_CREATED,
        // PAYMENT_SUBMITTED, PAYMENT_VERIFIED, ORDER_CONFIRMED, ORDER_READY,
        // ORDER_DISPATCHED, ORDER_DELIVERED, ORDER_CANCELLED,
        // REFUND_COMPLETED) - what a future agent's "get recent events" tool
        // actually reads from, instead of ever querying orders/payments/
        // deliveries directly ("agents must NOT directly access the
        // database"). Append-only, no updated_at. `payload` is deliberately
        // small (a handful of scalar identifiers per event, never a
        // serialized model) - the same "optimize for low AI token usage"
        // principle Phase 12's chatbot context-bounding already established.
        Schema::create('agent_event_logs', function (Blueprint $table) {
            $table->id();
            $table->string('event_type')->index();
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('payload');
            $table->timestamp('occurred_at')->useCurrent();

            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_event_logs');
    }
};
