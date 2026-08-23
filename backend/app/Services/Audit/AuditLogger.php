<?php

namespace App\Services\Audit;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Single call site for CLAUDE.md §15: every state-changing admin/staff
 * action - order status, refunds, product changes, role/permission
 * changes - is recorded with who/what/when and before/after values.
 * Deliberately not used for routine reads or trivial updates; call sites
 * are the operations explicitly called out in §15 and §5.
 */
class AuditLogger
{
    /**
     * Defense-in-depth for CLAUDE.md's "never log" list (passwords, API
     * keys, OTPs, payment credentials, secrets). Today no model actually
     * carries these as columns (payments/payment_transactions have a
     * dedicated schema test asserting that), so this never fires in
     * practice - but a snapshot is `$model->toArray()` of whatever the
     * caller passes, and relying solely on "the models happen not to have
     * these fields" is exactly the kind of implicit guarantee that breaks
     * silently the day someone adds one. Matched by key name, not value
     * shape, so it also catches a field never anticipated here.
     */
    private const REDACTED_KEYS = [
        'password', 'password_confirmation', 'current_password',
        'api_key', 'api_secret', 'secret', 'secret_key', 'client_secret',
        'token', 'access_token', 'refresh_token', 'auth_token',
        'otp', 'otp_code', 'pin', 'cvv', 'cvc', 'card_number', 'card_cvv',
        'two_factor_secret', 'two_factor_recovery_codes', 'recovery_codes',
        'gateway_secret', 'private_key',
    ];

    private const REDACTED_VALUE = '[redacted]';

    public static function log(
        string $action,
        ?Model $subject = null,
        array $before = [],
        array $after = [],
        ?Request $request = null,
    ): AuditLog {
        $request ??= request();

        return AuditLog::create([
            'user_id' => $request->user()?->id,
            'action' => $action,
            'auditable_type' => $subject?->getMorphClass(),
            'auditable_id' => $subject?->getKey(),
            'before' => $before ? self::redact($before) : null,
            'after' => $after ? self::redact($after) : null,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }

    /** @return array<mixed> */
    private static function redact(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_string($key) && in_array(strtolower($key), self::REDACTED_KEYS, true)) {
                $data[$key] = self::REDACTED_VALUE;
            } elseif (is_array($value)) {
                $data[$key] = self::redact($value);
            }
        }

        return $data;
    }
}
