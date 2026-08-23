<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Structural distinction between human staff/customers and AI
            // agent identities. Enforced in code (EnsureUserIsHuman
            // middleware) so an AI agent can never reach admin routes even
            // if it were mistakenly granted an overlapping permission.
            $table->enum('type', ['human', 'ai_agent'])->default('human')->after('id');

            // Bumped on password change or an explicit "log out everywhere"
            // action. Embedded as a claim in every access token, so a
            // stolen token becomes worthless immediately instead of staying
            // valid until its (short) natural expiry.
            $table->unsignedInteger('token_version')->default(0)->after('password');

            // MFA/2FA architecture (schema only - see AuthController and
            // ADR 0003 for the intended login-flow hook). No TOTP library,
            // QR provisioning, or SMS gateway added in this phase.
            $table->text('two_factor_secret')->nullable()->after('token_version');
            $table->text('two_factor_recovery_codes')->nullable()->after('two_factor_secret');
            $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_recovery_codes');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'type', 'token_version', 'two_factor_secret',
                'two_factor_recovery_codes', 'two_factor_confirmed_at',
            ]);
        });
    }
};
