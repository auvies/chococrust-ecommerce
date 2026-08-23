<?php

namespace Tests\Feature\Auth;

use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    public function test_responses_carry_the_baseline_security_headers(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('Content-Security-Policy', "default-src 'none'; frame-ancestors 'none'");
        $response->assertHeaderMissing('X-Powered-By');
    }

    public function test_headers_are_present_even_on_error_responses(): void
    {
        $response = $this->getJson('/api/v1/auth/me'); // unauthenticated -> 401

        $response->assertStatus(401);
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
    }
}
