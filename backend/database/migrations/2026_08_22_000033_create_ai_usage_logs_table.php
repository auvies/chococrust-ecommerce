<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Cost/token accounting (CLAUDE.md §19) and AI tool-call audit trail
        // (CLAUDE.md §9), kept separate from the raw chat_messages transcript.
        // Append-only - no updated_at.
        Schema::create('ai_usage_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chat_conversation_id')->nullable()->constrained('chat_conversations')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('provider');
            $table->string('model');
            $table->string('purpose')->index();
            $table->unsignedInteger('input_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->decimal('cost_usd', 10, 6)->nullable();
            $table->string('tool_name')->nullable();
            $table->json('tool_input')->nullable();
            $table->json('tool_output')->nullable();
            $table->enum('status', ['success', 'error', 'refused'])->default('success');
            $table->timestamp('created_at')->useCurrent();

            $table->index('chat_conversation_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_usage_logs');
    }
};
