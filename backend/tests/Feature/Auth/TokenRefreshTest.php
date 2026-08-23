<?php

namespace Tests\Feature\Auth;

use App\Models\RefreshToken;
use App\Support\Jwt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\InteractsWithRoles;
use Tests\TestCase;

class TokenRefreshTest extends TestCase
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
            'user' => $user,
            'access' => $response->getCookie('cc_access_token', false)->getValue(),
            'refresh' => $response->getCookie('cc_refresh_token', false)->getValue(),
            'csrf' => $response->getCookie('cc_csrf_token', false)->getValue(),
        ];
    }

    public function test_refresh_rotates_the_refresh_token_and_issues_a_new_access_token(): void
    {
        $session = $this->login();

        $response = $this->withUnencryptedCookie('cc_refresh_token', $session['refresh'])
            ->withUnencryptedCookie('cc_csrf_token', $session['csrf'])
            ->withHeader('X-CSRF-Token', $session['csrf'])
            ->postJson('/api/v1/auth/refresh');

        $response->assertOk();

        $newAccess = $response->getCookie('cc_access_token', false)->getValue();
        $newRefresh = $response->getCookie('cc_refresh_token', false)->getValue();

        $this->assertNotSame($session['access'], $newAccess);
        $this->assertNotSame($session['refresh'], $newRefresh);

        $oldRow = RefreshToken::where('token_hash', hash('sha256', $session['refresh']))->firstOrFail();
        $this->assertNotNull($oldRow->revoked_at, 'The old refresh token must be revoked once rotated.');
    }

    public function test_reusing_an_already_rotated_refresh_token_revokes_the_whole_session(): void
    {
        $session = $this->login();

        // First refresh: legitimate, rotates the token.
        $this->withUnencryptedCookie('cc_refresh_token', $session['refresh'])
            ->withUnencryptedCookie('cc_csrf_token', $session['csrf'])
            ->withHeader('X-CSRF-Token', $session['csrf'])
            ->postJson('/api/v1/auth/refresh')
            ->assertOk();

        // Second use of the SAME (now-rotated-out) refresh token: treated
        // as a theft signal, not just a stale-token error.
        $response = $this->withUnencryptedCookie('cc_refresh_token', $session['refresh'])
            ->withUnencryptedCookie('cc_csrf_token', $session['csrf'])
            ->withHeader('X-CSRF-Token', $session['csrf'])
            ->postJson('/api/v1/auth/refresh');

        $response->assertStatus(401);

        $user = $session['user']->fresh();
        $this->assertSame(0, RefreshToken::where('user_id', $user->id)->whereNull('revoked_at')->count());
    }

    public function test_refresh_without_a_refresh_token_cookie_is_rejected(): void
    {
        // A matching CSRF cookie/header pair, but no refresh_token cookie
        // at all - isolates the "no refresh token" 401 from the CSRF 419
        // that would otherwise fire first.
        $response = $this->withUnencryptedCookie('cc_csrf_token', 'test-csrf-value')
            ->withHeader('X-CSRF-Token', 'test-csrf-value')
            ->postJson('/api/v1/auth/refresh');

        $response->assertStatus(401);
    }

    public function test_an_expired_access_token_is_rejected_by_protected_routes(): void
    {
        $user = $this->makeUserWithRole('customer');

        $expired = Jwt::encode([
            'sub' => $user->id,
            'tv' => $user->token_version,
            'typ' => 'access',
            'iat' => now()->subMinutes(30)->timestamp,
            'exp' => now()->subMinutes(15)->timestamp,
        ], (string) config('security.jwt_secret'));

        $response = $this->withUnencryptedCookie('cc_access_token', $expired)->getJson('/api/v1/auth/me');

        $response->assertStatus(401);
    }

    public function test_bumping_token_version_invalidates_a_still_unexpired_access_token(): void
    {
        $session = $this->login();

        $session['user']->bumpTokenVersion();

        $response = $this->withUnencryptedCookie('cc_access_token', $session['access'])->getJson('/api/v1/auth/me');

        $response->assertStatus(401);
    }

    public function test_a_valid_unexpired_access_token_is_accepted(): void
    {
        // Positive control for the two tests above: proves the cookie is
        // actually being sent and read, not merely absent either way.
        $session = $this->login();

        $response = $this->withUnencryptedCookie('cc_access_token', $session['access'])->getJson('/api/v1/auth/me');

        $response->assertOk();
        $response->assertJsonPath('id', $session['user']->id);
    }
}
