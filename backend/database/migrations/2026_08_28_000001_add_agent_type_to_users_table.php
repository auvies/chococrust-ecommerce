<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Which of the 8 named future-agent types this ai_agent-type
            // identity is provisioned as (see App\Services\Agents\AgentType) -
            // null for an unscoped/general agent identity (e.g. the existing
            // chatbot's tool-calling identity, which may call any tool with
            // an empty allowedAgentTypes() allow-list). A plain nullable
            // string, not a DB enum - the same "business decision, not a
            // fixed set" reasoning `payments.method`/`users.type`'s sibling
            // columns already established, since the named agent list is
            // expected to grow.
            $table->string('agent_type')->nullable()->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('agent_type');
        });
    }
};
