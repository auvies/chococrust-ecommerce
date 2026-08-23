<?php

namespace App\Services\Notifications;

use App\Models\NotificationTemplate;

/**
 * Resolves a notification's rendered subject/body from the admin-editable
 * `notification_templates` table instead of hardcoding copy in each
 * Notification class. `{placeholder}` tokens in `subject`/`body` are
 * replaced from the data array the caller supplies - unknown placeholders
 * are left as-is rather than throwing, so an admin editing a template
 * can't accidentally break the send by mistyping a token name.
 *
 * Returns null when no active template exists for the (key, channel) pair
 * so callers can fall back to a safe hardcoded default (CLAUDE.md §16 -
 * a missing/deactivated template must never break the underlying order/
 * payment/delivery flow it's attached to).
 */
class NotificationTemplateService
{
    /** @return array{subject: ?string, body: string}|null */
    public function render(string $key, string $channel, array $data): ?array
    {
        $template = NotificationTemplate::query()
            ->where('key', $key)
            ->where('channel', $channel)
            ->where('is_active', true)
            ->first();

        if (! $template) {
            return null;
        }

        return [
            'subject' => $this->interpolate($template->subject, $data),
            'body' => $this->interpolate($template->body, $data) ?? '',
        ];
    }

    private function interpolate(?string $text, array $data): ?string
    {
        if ($text === null) {
            return null;
        }

        return preg_replace_callback(
            '/\{(\w+)\}/',
            fn (array $matches) => array_key_exists($matches[1], $data) ? (string) $data[$matches[1]] : $matches[0],
            $text,
        );
    }
}
