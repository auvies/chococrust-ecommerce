<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\InteractsWithRoles;
use Tests\TestCase;

class CsrfProtectionTest extends TestCase
{
    use InteractsWithRoles, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->withCredentials();
    }

    private function login(): array
    {
        $user = $this->makeUserWithRole('customer', ['password' => Hash::make('Str0ngPass!')]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'Str0ngPass!',
        ]);

        return [
            'access' => $response->getCookie('cc_access_token', false)->getValue(),
            'refresh' => $response->getCookie('cc_refresh_token', false)->getValue(),
            'csrf' => $response->getCookie('cc_csrf_token', false)->getValue(),
        ];
    }

    public function test_login_and_register_do_not_require_a_csrf_header(): void
    {
        // No prior session exists yet at these entry points, so there is
        // no CSRF cookie to echo back - they're intentionally exempt.
        $this->postJson('/api/v1/auth/register', [
            'name' => 'A',
            'email' => 'a@example.com',
            'password' => 'Str0ngPass!',
            'password_confirmation' => 'Str0ngPass!',
        ])->assertCreated();
    }

    public function test_mismatched_csrf_header_and_cookie_are_rejected(): void
    {
        $session = $this->login();

        $response = $this->withUnencryptedCookie('cc_refresh_token', $session['refresh'])
            ->withUnencryptedCookie('cc_csrf_token', $session['csrf'])
            ->withHeader('X-CSRF-Token', 'a-completely-different-value')
            ->postJson('/api/v1/auth/refresh');

        $response->assertStatus(419);
    }

    public function test_missing_csrf_cookie_is_rejected_even_with_a_header_present(): void
    {
        $session = $this->login();

        $response = $this->withUnencryptedCookie('cc_refresh_token', $session['refresh'])
            ->withHeader('X-CSRF-Token', $session['csrf']) // header only, no cookie
            ->postJson('/api/v1/auth/refresh');

        $response->assertStatus(419);
    }

    public function test_matching_csrf_cookie_and_header_are_accepted(): void
    {
        $session = $this->login();

        $response = $this->withUnencryptedCookie('cc_refresh_token', $session['refresh'])
            ->withUnencryptedCookie('cc_csrf_token', $session['csrf'])
            ->withHeader('X-CSRF-Token', $session['csrf'])
            ->postJson('/api/v1/auth/refresh');

        $response->assertOk();
    }
}
