<?php

namespace App\Support;

use App\Exceptions\InvalidTokenException;

/**
 * Minimal HS256 JWT encode/decode.
 *
 * Why hand-rolled instead of a Composer package: this sandbox has no
 * outbound network/Composer access to add one, and the format needed here
 * is small enough to implement and audit directly. The algorithm is
 * hardcoded on both sides rather than read from the token's own header,
 * which closes the classic "alg" confusion / "alg: none" vulnerability
 * class outright - there is no code path that trusts an attacker-supplied
 * algorithm. Signature comparison is constant-time (hash_equals).
 *
 * This is deliberately NOT a general-purpose JWT library: no algorithm
 * negotiation, no JWK/JWKS, no asymmetric keys. Just enough to issue and
 * verify this application's own short-lived access tokens.
 */
class Jwt
{
    private const ALG = 'HS256';

    /**
     * @param  array<string, mixed>  $claims
     */
    public static function encode(array $claims, string $secret): string
    {
        $header = self::base64UrlEncode(json_encode(['alg' => self::ALG, 'typ' => 'JWT'], JSON_THROW_ON_ERROR));
        $payload = self::base64UrlEncode(json_encode($claims, JSON_THROW_ON_ERROR));
        $signature = self::sign("{$header}.{$payload}", $secret);

        return "{$header}.{$payload}.{$signature}";
    }

    /**
     * @return array<string, mixed>
     *
     * @throws InvalidTokenException
     */
    public static function decode(string $token, string $secret): array
    {
        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            throw new InvalidTokenException('Malformed token.');
        }

        [$header, $payload, $signature] = $parts;

        $expectedSignature = self::sign("{$header}.{$payload}", $secret);

        if (! hash_equals($expectedSignature, $signature)) {
            throw new InvalidTokenException('Signature mismatch.');
        }

        $decodedHeader = json_decode(self::base64UrlDecode($header), true);

        if (! is_array($decodedHeader) || ($decodedHeader['alg'] ?? null) !== self::ALG) {
            // The signature already matched under our own hardcoded
            // algorithm above, so this only catches a malformed header -
            // the algorithm itself was never taken from attacker input.
            throw new InvalidTokenException('Unsupported token header.');
        }

        $claims = json_decode(self::base64UrlDecode($payload), true);

        if (! is_array($claims)) {
            throw new InvalidTokenException('Malformed claims.');
        }

        if (isset($claims['exp']) && time() >= (int) $claims['exp']) {
            throw new InvalidTokenException('Token expired.');
        }

        if (isset($claims['nbf']) && time() < (int) $claims['nbf']) {
            throw new InvalidTokenException('Token not yet valid.');
        }

        return $claims;
    }

    private static function sign(string $data, string $secret): string
    {
        return self::base64UrlEncode(hash_hmac('sha256', $data, $secret, binary: true));
    }

    private static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $data): string
    {
        $remainder = strlen($data) % 4;
        $padded = $remainder === 0 ? $data : $data.str_repeat('=', 4 - $remainder);

        $decoded = base64_decode(strtr($padded, '-_', '+/'), strict: true);

        if ($decoded === false) {
            throw new InvalidTokenException('Invalid base64url segment.');
        }

        return $decoded;
    }
}
