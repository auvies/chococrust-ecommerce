<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // CLAUDE.md §13: idempotency for payment-affecting and
        // order-mutating endpoints where retries are possible. A client
        // resends the same Idempotency-Key header on retry; the second
        // request replays the first's stored response instead of
        // re-executing the side effect.
        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->string('route');
            $table->string('key');
            $table->unsignedSmallInteger('response_status');
            $table->json('response_body');
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->unique(['user_id', 'route', 'key'], 'ux_idempotency_scope');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
    }
};
