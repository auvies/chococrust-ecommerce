<?php

namespace App\Auth;

use App\Exceptions\InvalidTokenException;
use App\Support\Jwt;
use Illuminate\Auth\GuardHelpers;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Http\Request;

/**
 * Resolves the authenticated user from the httpOnly access-token cookie
 * (CLAUDE.md §7) instead of a session or a Bearer header. A resolved user
 * additionally requires: account still active, not soft-deleted, and the
 * token's "tv" (token_version) claim matching the user's current
 * token_version - so a forced logout-everywhere invalidates every
 * previously-issued access token immediately, not just at its natural
 * (short) expiry.
 */
class JwtCookieGuard implements Guard
{
    use GuardHelpers;

    private bool $resolved = false;

    public function __construct(
        private readonly Request $request,
        UserProvider $provider,
    ) {
        $this->provider = $provider;
    }

    public function user(): ?Authenticatable
    {
        if ($this->resolved) {
            return $this->user;
        }

        $this->resolved = true;
        $this->user = $this->resolveFromCookie();

        return $this->user;
    }

    /**
     * Overridden (not just inherited from GuardHelpers) so that
     * setUser() - as called directly by test helpers like actingAs() -
     * short-circuits cookie resolution entirely instead of being
     * clobbered by the first user() call.
     */
    public function setUser(Authenticatable $user): static
    {
        $this->user = $user;
        $this->resolved = true;

        return $this;
    }

    public function validate(array $credentials = []): bool
    {
        return false;
    }

    private function resolveFromCookie(): ?Authenticatable
    {
        $cookieName = config('security.cookie.access_name');
        $token = $this->request->cookie($cookieName);

        if (! is_string($token) || $token === '') {
            return null;
        }

        try {
            $claims = Jwt::decode($token, (string) config('security.jwt_secret'));
        } catch (InvalidTokenException) {
            return null;
        }

        if (($claims['typ'] ?? null) !== 'access' || ! isset($claims['sub'])) {
            return null;
        }

        $user = $this->provider->retrieveById($claims['sub']);

        if (! $user) {
            return null;
        }

        if (! $user->is_active) {
            return null;
        }

        if ((int) $user->token_version !== (int) ($claims['tv'] ?? -1)) {
            return null;
        }

        return $user;
    }
}
