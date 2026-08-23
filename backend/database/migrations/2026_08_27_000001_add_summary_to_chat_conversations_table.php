<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_conversations', function (Blueprint $table) {
            // A deterministic (non-AI, zero-cost) rolling compaction of
            // older messages - ChatSummarizationService keeps this
            // refreshed once a conversation grows past the verbatim
            // history window, so the AI is never sent the full transcript
            // on every request (the explicit cost rule this phase exists
            // to satisfy).
            $table->text('summary')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('chat_conversations', function (Blueprint $table) {
            $table->dropColumn('summary');
        });
    }
};
