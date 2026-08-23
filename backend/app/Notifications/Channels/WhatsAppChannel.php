<?php

namespace App\Notifications\Channels;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

/**
 * WhatsApp integration preparation (CLAUDE.md §6/§9 posture - "prepared,
 * not built", the same stance Phase 09/ADR 0009 took on Easypaisa/JazzCash
 * and Phase 03/ADR 0003 took on MFA): no WhatsApp Business API account or
 * credentials exist yet, so this channel never makes an outbound HTTP call.
 * It logs the fully-rendered message instead - the data model, the
 * template pipeline (NotificationTemplateService), and every trigger point
 * (order/payment/delivery status changes) are real and exercised end to
 * end; only the final "send it over the wire" step is deliberately a
 * no-op pending an actual account.
 *
 * Wiring a real provider later (e.g. Twilio's WhatsApp API, or Meta's
 * Cloud API) means implementing the `send()` body here only - callers,
 * `via()` methods, and `toWhatsApp()` payloads on every Notification class
 * never need to change.
 */
class WhatsAppChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toWhatsApp')) {
            return;
        }

        $message = $notification->toWhatsApp($notifiable);

        if (empty($message['to'])) {
            return;
        }

        if (! config('services.whatsapp.enabled')) {
            return;
        }

        // Never the phone number or the rendered body in plaintext
        // (CLAUDE.md §4/§15 - PII never appears in plaintext application
        // logs) - a security-audit fix. This channel is a no-op today (no
        // real provider call happens either way), but the logging pattern
        // itself must be safe from the moment it's written, not patched
        // only once WHATSAPP_ENABLED is actually flipped on for real
        // customer traffic.
        Log::info('whatsapp.notification.prepared', [
            'to' => self::maskPhone($message['to']),
            'body_length' => isset($message['body']) ? strlen((string) $message['body']) : 0,
        ]);
    }

    /** Keeps only the last 4 digits - enough to spot-check in a log without persisting a full, PII-bearing phone number. */
    private static function maskPhone(string $phone): string
    {
        return strlen($phone) > 4
            ? str_repeat('*', strlen($phone) - 4).substr($phone, -4)
            : str_repeat('*', strlen($phone));
    }
}
