<?php

namespace Tests\Feature\Auth;

use App\Models\RefreshToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\InteractsWithRoles;
use Tests\TestCase;

class LogoutTest extends TestCase
{
    use InteractsWithRoles, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        // Cookies are otherwise stripped from postJson()/getJson() (they
        // mirror fetch()'s credentials:'omit' default) and withCookie()
        // encrypts its value expecting Laravel's EncryptCookies middleware
        // - which this cookie-based-but-stateless API deliberately doesn't
        // run (see bootstrap/app.php). withUnencryptedCookie() is the
        // correct counterpart here.
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
            'user' => $user,
            'access' => $response->getCookie('cc_access_token', false)->getValue(),
            'refresh' => $response->getCookie('cc_refresh_token', false)->getValue(),
            'csrf' => $response->getCookie('cc_csrf_token', false)->getValue(),
        ];
    }

    public function test_logout_requires_a_matching_csrf_header(): void
    {
        $session = $this->login();

        $response = $this->withUnencryptedCookie('cc_access_token', $session['access'])
            ->withUnencryptedCookie('cc_refresh_token', $session['refresh'])
            ->withUnencryptedCookie('cc_csrf_token', $session['csrf'])
            // No X-CSRF-Token header at all.
            ->postJson('/api/v1/auth/logout');

        $response->assertStatus(419);
    }

    public function test_logout_revokes_the_refresh_token_and_clears_cookies(): void
    {
        $session = $this->login();

        $response = $this->withUnencryptedCookie('cc_access_token', $session['access'])
            ->withUnencryptedCookie('cc_refresh_token', $session['refresh'])
            ->withUnencryptedCookie('cc_csrf_token', $session['csrf'])
            ->withHeader('X-CSRF-Token', $session['csrf'])
            ->postJson('/api/v1/auth/logout');

        $response->assertStatus(204);

        $hash = hash('sha256', $session['refresh']);
        $row = RefreshToken::where('token_hash', $hash)->firstOrFail();
        $this->assertNotNull($row->revoked_at);

        // The cookies in the response are explicitly expired.
        $cleared = $response->getCookie('cc_access_token', false);
        $this->assertNotNull($cleared);
        $this->assertTrue($cleared->getExpiresTime() < time());
    }

    public function test_a_revoked_refresh_token_can_no_longer_be_used_to_refresh(): void
    {
        $session = $this->login();

        $this->withUnencryptedCookie('cc_access_token', $session['access'])
            ->withUnencryptedCookie('cc_refresh_token', $session['refresh'])
            ->withUnencryptedCookie('cc_csrf_token', $session['csrf'])
            ->withHeader('X-CSRF-Token', $session['csrf'])
            ->postJson('/api/v1/auth/logout')
            ->assertStatus(204);

        $response = $this->withUnencryptedCookie('cc_refresh_token', $session['refresh'])
            ->withUnencryptedCookie('cc_csrf_token', $session['csrf'])
            ->withHeader('X-CSRF-Token', $session['csrf'])
            ->postJson('/api/v1/auth/refresh');

        $response->assertStatus(401);
    }

    public function test_logout_without_authentication_is_rejected(): void
    {
        $response = $this->postJson('/api/v1/auth/logout');

        $response->assertStatus(401);
    }
}
