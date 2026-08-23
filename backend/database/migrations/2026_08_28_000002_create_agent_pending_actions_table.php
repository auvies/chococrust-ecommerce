<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The human-approval queue for high-risk agent tool calls (CLAUDE.md
        // §9/§23 and this phase's own brief: refund, payment verification,
        // large discount, price changes, bulk deletion, order cancellation,
        // dispatch cancellation). A high-risk tool call never executes
        // immediately - it creates one of these instead, and only a
        // reviewer's explicit approve() actually runs the underlying
        // business logic (see AgentApprovalService).
        Schema::create('agent_pending_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_user_id')->constrained('users')->nullOnDelete();
            $table->string('tool_name');
            // Snapshot of the requesting identity's agent type at request
            // time, kept even if the account's type is later reassigned.
            $table->string('agent_type')->nullable();
            $table->json('input');
            $table->string('risk_reason')->nullable();
            // pending -> executed | rejected | failed. Not a native DB enum
            // (same reasoning as payments.status, ADR 0009) - the set of
            // outcomes is an application decision, not a schema one.
            $table->string('status')->default('pending')->index();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('rejection_reason')->nullable();
            $table->json('result')->nullable();
            $table->string('error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_pending_actions');
    }
};
