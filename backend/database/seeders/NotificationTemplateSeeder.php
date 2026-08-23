<?php

namespace Database\Seeders;

use App\Models\NotificationTemplate;
use Illuminate\Database\Seeder;

/**
 * One row per (event key, channel) combination each Notification class in
 * app/Notifications actually renders (see CustomerFacingNotification).
 * `firstOrCreate` keyed on key+channel, matching the established
 * CategorySeeder/HomepageSectionSeeder/DeliveryRuleSeeder convention - safe
 * to re-run in every environment, never overwrites an admin's later edit
 * through the Notification Templates admin screen.
 */
class NotificationTemplateSeeder extends Seeder
{
    private const TEMPLATES = [
        [
            'key' => 'order.placed',
            'channel' => 'database',
            'subject' => null,
            'body' => 'Thanks for your order! {order_number} has been placed and is now being processed.',
        ],
        [
            'key' => 'order.placed',
            'channel' => 'mail',
            'subject' => 'Order {order_number} confirmed',
            'body' => "Hi,\n\nThanks for your order! {order_number} (total {currency} {total}) has been placed and is now being processed. We'll let you know as it moves along.",
        ],
        [
            'key' => 'order.placed',
            'channel' => 'whatsapp',
            'subject' => null,
            'body' => 'Choco Crust: your order {order_number} has been placed. We will keep you updated.',
        ],
        [
            'key' => 'order.status_changed',
            'channel' => 'database',
            'subject' => null,
            'body' => 'Your order {order_number} is now {to_status}.',
        ],
        [
            'key' => 'order.status_changed',
            'channel' => 'mail',
            'subject' => 'Order {order_number} update',
            'body' => "Hi,\n\nYour order {order_number} has moved from {from_status} to {to_status}.",
        ],
        [
            'key' => 'order.status_changed',
            'channel' => 'whatsapp',
            'subject' => null,
            'body' => 'Choco Crust: order {order_number} is now {to_status}.',
        ],
        [
            'key' => 'payment.status_changed',
            'channel' => 'database',
            'subject' => null,
            'body' => 'The payment for order {order_number} is now {to_status}.',
        ],
        [
            'key' => 'payment.status_changed',
            'channel' => 'mail',
            'subject' => 'Payment update for order {order_number}',
            'body' => "Hi,\n\nThe payment for your order {order_number} is now {to_status}.",
        ],
        [
            'key' => 'payment.status_changed',
            'channel' => 'whatsapp',
            'subject' => null,
            'body' => 'Choco Crust: payment for order {order_number} is now {to_status}.',
        ],
        [
            'key' => 'delivery.status_changed',
            'channel' => 'database',
            'subject' => null,
            'body' => 'The delivery for order {order_number} is now {status}.',
        ],
        [
            'key' => 'delivery.status_changed',
            'channel' => 'mail',
            'subject' => 'Delivery update for order {order_number}',
            'body' => "Hi,\n\nThe delivery for your order {order_number} is now {status}.",
        ],
        [
            'key' => 'delivery.status_changed',
            'channel' => 'whatsapp',
            'subject' => null,
            'body' => 'Choco Crust: delivery for order {order_number} is now {status}.',
        ],
    ];

    public function run(): void
    {
        foreach (self::TEMPLATES as $template) {
            NotificationTemplate::firstOrCreate(
                ['key' => $template['key'], 'channel' => $template['channel']],
                ['subject' => $template['subject'], 'body' => $template['body'], 'is_active' => true],
            );
        }
    }
}
