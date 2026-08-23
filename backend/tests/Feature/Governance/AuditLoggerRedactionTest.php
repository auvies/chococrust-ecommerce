<?php

namespace Tests\Feature\Governance;

use App\Services\Audit\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * CLAUDE.md's "never log" list (passwords, API keys, OTPs, payment
 * credentials, secrets), enforced as a code-level backstop on every audit
 * write - not just relied on because the models that get audited today
 * happen not to carry these columns (see AuditLogger's own docblock).
 */
class AuditLoggerRedactionTest extends TestCase
{
    use RefreshDatabase;

    public function test_denylisted_keys_are_redacted_from_before_and_after_snapshots(): void
    {
        $log = AuditLogger::log(
            'test.redaction',
            before: ['password' => 'secret123', 'name' => 'Jane'],
            after: ['api_key' => 'sk-abc123', 'otp' => '123456', 'name' => 'Jane Doe'],
        );

        $this->assertSame('[redacted]', $log->before['password']);
        $this->assertSame('Jane', $log->before['name']);
        $this->assertSame('[redacted]', $log->after['api_key']);
        $this->assertSame('[redacted]', $log->after['otp']);
        $this->assertSame('Jane Doe', $log->after['name']);
    }

    public function test_redaction_is_case_insensitive_and_recurses_into_nested_arrays(): void
    {
        $log = AuditLogger::log(
            'test.redaction',
            after: [
                'Password' => 'x',
                'API_KEY' => 'y',
                'card' => ['CVV' => '123', 'card_number' => '4111111111111111', 'brand' => 'visa'],
            ],
        );

        $this->assertSame('[redacted]', $log->after['Password']);
        $this->assertSame('[redacted]', $log->after['API_KEY']);
        $this->assertSame('[redacted]', $log->after['card']['CVV']);
        $this->assertSame('[redacted]', $log->after['card']['card_number']);
        $this->assertSame('visa', $log->after['card']['brand']);
    }

    public function test_an_empty_before_or_after_is_stored_as_null_not_an_empty_array(): void
    {
        $log = AuditLogger::log('test.redaction', before: [], after: ['name' => 'ok']);

        $this->assertNull($log->before);
        $this->assertSame(['name' => 'ok'], $log->after);
    }
}
