<?php

namespace Tests\Unit\Support;

use App\Exceptions\InvalidTokenException;
use App\Support\Jwt;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class JwtTest extends TestCase
{
    private const SECRET = 'unit-test-secret-do-not-use-in-real-envs';

    #[Test]
    public function it_round_trips_claims(): void
    {
        $token = Jwt::encode(['sub' => 42, 'exp' => time() + 60], self::SECRET);
        $claims = Jwt::decode($token, self::SECRET);

        $this->assertSame(42, $claims['sub']);
    }

    #[Test]
    public function it_rejects_a_tampered_payload(): void
    {
        $token = Jwt::encode(['sub' => 1, 'exp' => time() + 60], self::SECRET);
        [$header, $payload, $signature] = explode('.', $token);

        $forgedPayload = strtr(base64_encode(json_encode(['sub' => 999, 'exp' => time() + 60])), '+/', '-_');
        $forgedPayload = rtrim($forgedPayload, '=');

        $this->expectException(InvalidTokenException::class);
        Jwt::decode("{$header}.{$forgedPayload}.{$signature}", self::SECRET);
    }

    #[Test]
    public function it_rejects_a_token_signed_with_a_different_secret(): void
    {
        $token = Jwt::encode(['sub' => 1, 'exp' => time() + 60], 'a-different-secret');

        $this->expectException(InvalidTokenException::class);
        Jwt::decode($token, self::SECRET);
    }

    #[Test]
    public function it_rejects_an_expired_token(): void
    {
        $token = Jwt::encode(['sub' => 1, 'exp' => time() - 1], self::SECRET);

        $this->expectException(InvalidTokenException::class);
        Jwt::decode($token, self::SECRET);
    }

    #[Test]
    public function it_rejects_a_malformed_token(): void
    {
        $this->expectException(InvalidTokenException::class);
        Jwt::decode('not-a-jwt', self::SECRET);
    }

    #[Test]
    public function it_ignores_an_attacker_supplied_algorithm_header(): void
    {
        // Even if an attacker crafts a header claiming alg=none (or any
        // other algorithm), the server-side verification always signs
        // under its own hardcoded HS256 - a forged header can never make
        // decode() skip signature verification.
        $forgedHeader = rtrim(strtr(base64_encode(json_encode(['alg' => 'none', 'typ' => 'JWT'])), '+/', '-_'), '=');
        $payload = rtrim(strtr(base64_encode(json_encode(['sub' => 1, 'exp' => time() + 60])), '+/', '-_'), '=');

        $this->expectException(InvalidTokenException::class);
        Jwt::decode("{$forgedHeader}.{$payload}.", self::SECRET);
    }
}
