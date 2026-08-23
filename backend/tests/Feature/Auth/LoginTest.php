<?php

namespace Tests\Feature\Auth;

use App\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\InteractsWithRoles;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use InteractsWithRoles, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    public function test_login_with_correct_credentials_issues_cookies(): void
    {
        $user = $this->makeUserWithRole('customer', ['password' => Hash::make('Str0ngPass!')]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'Str0ngPass!',
        ]);

        $response->assertOk();
        $access = $response->getCookie('cc_access_token', false);
        $refresh = $response->getCookie('cc_refresh_token', false);
        $csrf = $response->getCookie('cc_csrf_token', false);

        $this->assertNotNull($access);
        $this->assertNotNull($refresh);
        $this->assertNotNull($csrf);

        // CLAUDE.md §7: httpOnly, Secure, SameSite - never readable by JS
        // for the access/refresh tokens; the CSRF cookie is the one
        // deliberate exception (JS must read it to echo it back).
        $this->assertTrue($access->isHttpOnly());
        $this->assertTrue($access->isSecure());
        $this->assertSame('lax', strtolower((string) $access->getSameSite()));
        $this->assertTrue($refresh->isHttpOnly());
        $this->assertFalse($csrf->isHttpOnly());

        $this->assertNotNull(
            AuditLog::where('action', 'auth.login_succeeded')->where('user_id', $user->id)->first()
        );
    }

    public function test_login_response_never_contains_a_token_or_password(): void
    {
        $user = $this->makeUserWithRole('customer', ['password' => Hash::make('Str0ngPass!')]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'Str0ngPass!',
        ]);

        $response->assertJsonMissing(['password', 'access_token', 'refresh_token', 'token']);
    }

    public function test_login_with_wrong_password_is_rejected_with_a_generic_message(): void
    {
        $user = $this->makeUserWithRole('customer', ['password' => Hash::make('Str0ngPass!')]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'WrongPassword!',
        ]);

        $response->assertStatus(401);
        // Never reveals whether it was the email or the password that was wrong.
        $this->assertStringNotContainsStringIgnoringCase('password', (string) $response->json('message'));

        $this->assertNotNull(
            AuditLog::where('action', 'auth.login_failed')->where('auditable_id', $user->id)->first()
        );
    }

    public function test_login_for_unknown_email_returns_the_same_generic_error(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'nobody@example.com',
            'password' => 'WhateverPass1',
        ]);

        $response->assertStatus(401);
    }

    public function test_a_disabled_account_cannot_log_in(): void
    {
        $user = $this->makeUserWithRole('customer', [
            'password' => Hash::make('Str0ngPass!'),
            'is_active' => false,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'Str0ngPass!',
        ]);

        $response->assertStatus(403);
    }

    public function test_an_ai_agent_account_cannot_log_in_with_a_password(): void
    {
        $agent = $this->makeAiAgent(['password' => Hash::make('Str0ngPass!')]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $agent->email,
            'password' => 'Str0ngPass!',
        ]);

        $response->assertStatus(401);
    }

    public function test_repeated_failed_logins_are_rate_limited(): void
    {
        $user = $this->makeUserWithRole('customer', ['password' => Hash::make('Str0ngPass!')]);
        $maxAttempts = config('security.login_throttle.max_attempts');

        for ($i = 0; $i < $maxAttempts; $i++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => $user->email,
                'password' => 'WrongPassword!',
            ])->assertStatus(401);
        }

        // One more attempt, even with the CORRECT password, is locked out.
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'Str0ngPass!',
        ]);

        $response->assertStatus(429);
    }
}
