<?php

namespace App\Notifications;

use App\Notifications\Channels\WhatsAppChannel;
use App\Services\Notifications\NotificationTemplateService;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Shared shape for order/payment/delivery notifications sent to a
 * customer's User account: same channel-selection rule (database always,
 * mail when there's an email, WhatsApp when there's a phone number and
 * the channel is enabled - see WhatsAppChannel), and the same
 * template-then-fallback rendering (NotificationTemplateService, falling
 * back to a hardcoded message so a missing/deactivated template can never
 * silently drop the notification - CLAUDE.md §16).
 */
abstract class CustomerFacingNotification extends Notification
{
    abstract protected function templateKey(): string;

    /** @return array<string, mixed> */
    abstract protected function data(): array;

    abstract protected function fallbackBody(): string;

    protected function fallbackSubject(): string
    {
        return (string) config('app.name').' - order update';
    }

    public function via(object $notifiable): array
    {
        return array_values(array_filter([
            'database',
            $notifiable->email ? 'mail' : null,
            $notifiable->phone ? WhatsAppChannel::class : null,
        ]));
    }

    public function toDatabase(object $notifiable): array
    {
        $rendered = app(NotificationTemplateService::class)->render($this->templateKey(), 'database', $this->data());

        return ['body' => $rendered['body'] ?? $this->fallbackBody()] + $this->data();
    }

    public function toMail(object $notifiable): MailMessage
    {
        $rendered = app(NotificationTemplateService::class)->render($this->templateKey(), 'mail', $this->data());

        return (new MailMessage)
            ->subject($rendered['subject'] ?? $this->fallbackSubject())
            ->line($rendered['body'] ?? $this->fallbackBody());
    }

    /** @return array{to: ?string, body: string} */
    public function toWhatsApp(object $notifiable): array
    {
        $rendered = app(NotificationTemplateService::class)->render($this->templateKey(), 'whatsapp', $this->data());

        return [
            'to' => $notifiable->phone,
            'body' => $rendered['body'] ?? $this->fallbackBody(),
        ];
    }
}
