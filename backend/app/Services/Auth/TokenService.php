<?php

namespace App\Services\Auth;

use App\Exceptions\InvalidTokenException;
use App\Models\AuditLog;
use App\Models\RefreshToken;
use App\Models\User;
use App\Support\Jwt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

/**
 * Issues, rotates, and revokes the access/refresh/CSRF token triplet
 * (CLAUDE.md §7): a short-lived stateless JWT access token, a longer-lived
 * opaque refresh token stored hashed in the database (so it's individually
 * revocable), and a CSRF double-submit token. All three travel as cookies -
 * never returned in a JSON body, never meant for localStorage.
 */
class TokenService
{
    public function issueForUser(User $user, Request $request): array
    {
        return $this->issue($user, $request);
    }

    /**
     * Validates and rotates a presented refresh token: the old row is
     * revoked and a brand-new pair is issued. A refresh token that's
     * already been rotated out (reused) is treated as a possible theft
     * signal - every other active token for that user is revoked too.
     *
     * @throws InvalidTokenException
     */
    public function rotate(string $rawRefreshToken, Request $request): array
    {
        $hash = self::hash($rawRefreshToken);
        $tokenRow = RefreshToken::where('token_hash', $hash)->first();

        if (! $tokenRow) {
            throw new InvalidTokenException('Unknown refresh token.');
        }

        if ($tokenRow->revoked_at !== null) {
            $this->revokeAllForUser($tokenRow->user, reason: 'refresh_token_reuse_detected', request: $request);

            throw new InvalidTokenException('Refresh token was already used.');
        }

        if ($tokenRow->expires_at->isPast()) {
            throw new InvalidTokenException('Refresh token expired.');
        }

        $tokenRow->forceFill(['revoked_at' => now(), 'last_used_at' => now()])->save();

        return $this->issue($tokenRow->user, $request);
    }

    public function revoke(string $rawRefreshToken): void
    {
        RefreshToken::where('token_hash', self::hash($rawRefreshToken))
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);
    }

    /** Logout-everywhere: revoke every refresh token and invalidate every live access token. */
    public function revokeAllForUser(User $user, string $reason = 'logout_all', ?Request $request = null): void
    {
        $user->refreshTokens()->whereNull('revoked_at')->update(['revoked_at' => now()]);
        $user->bumpTokenVersion();

        AuditLog::create([
            'user_id' => $user->id,
            'action' => "auth.{$reason}",
            'auditable_type' => 'user',
            'auditable_id' => $user->id,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
        ]);
    }

    public function attachCookies(array $tokens): void
    {
        $config = config('security.cookie');

        Cookie::queue(Cookie::make(
            $config['access_name'],
            $tokens['access'],
            (int) config('security.access_token_ttl'),
            '/',
            $config['domain'],
            $config['secure'],
            true, // httpOnly
            false,
            $config['same_site'],
        ));

        Cookie::queue(Cookie::make(
            $config['refresh_name'],
            $tokens['refresh'],
            (int) config('security.refresh_token_ttl'),
            '/api/v1/auth',
            $config['domain'],
            $config['secure'],
            true, // httpOnly
            false,
            $config['same_site'],
        ));

        // Not httpOnly: the frontend must read this and echo it back in
        // X-CSRF-Token on state-changing requests (see VerifyCsrfCookie).
        Cookie::queue(Cookie::make(
            $config['csrf_name'],
            $tokens['csrf'],
            (int) config('security.refresh_token_ttl'),
            '/',
            $config['domain'],
            $config['secure'],
            false,
            false,
            $config['same_site'],
        ));
    }

    public function clearCookies(): void
    {
        $config = config('security.cookie');

        Cookie::queue(Cookie::forget($config['access_name'], '/', $config['domain']));
        Cookie::queue(Cookie::forget($config['refresh_name'], '/api/v1/auth', $config['domain']));
        Cookie::queue(Cookie::forget($config['csrf_name'], '/', $config['domain']));
    }

    private function issue(User $user, Request $request): array
    {
        $now = now();
        $accessTtlSeconds = (int) config('security.access_token_ttl') * 60;

        $access = Jwt::encode([
            'sub' => $user->id,
            'tv' => $user->token_version,
            'typ' => 'access',
            'iat' => $now->timestamp,
            'exp' => $now->timestamp + $accessTtlSeconds,
            'jti' => Str::uuid()->toString(),
        ], (string) config('security.jwt_secret'));

        $rawRefresh = Str::random(64);

        RefreshToken::create([
            'user_id' => $user->id,
            'token_hash' => self::hash($rawRefresh),
            'device_name' => $request->userAgent(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'expires_at' => $now->clone()->addMinutes((int) config('security.refresh_token_ttl')),
        ]);

        return [
            'access' => $access,
            'refresh' => $rawRefresh,
            'csrf' => Str::random(40),
        ];
    }

    private static function hash(string $raw): string
    {
        return hash('sha256', $raw);
    }
}
