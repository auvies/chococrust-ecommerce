<?php

namespace Tests\Feature\Notifications;

use App\Models\Customer;
use App\Models\NotificationTemplate;
use App\Models\Order;
use App\Notifications\OrderStatusChanged;
use App\Services\Notifications\NotificationTemplateService;
use Database\Seeders\NotificationTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithRoles;
use Tests\TestCase;

class NotificationTemplateTest extends TestCase
{
    use InteractsWithRoles, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->seed(NotificationTemplateSeeder::class);
        $this->withCredentials();
    }

    private function withCsrf()
    {
        return $this->withUnencryptedCookie('cc_csrf_token', 'x')->withHeader('X-CSRF-Token', 'x');
    }

    public function test_the_seeder_produces_one_row_per_key_and_channel_and_is_idempotent(): void
    {
        $this->assertSame(12, NotificationTemplate::count());

        $this->seed(NotificationTemplateSeeder::class);

        $this->assertSame(12, NotificationTemplate::count());
    }

    public function test_listing_templates_requires_notifications_manage(): void
    {
        $support = $this->makeUserWithRole('support');
        $manager = $this->makeUserWithRole('manager');

        $this->actingAs($support, 'api')->getJson('/api/v1/notification-templates')->assertStatus(403);
        $this->actingAs($manager, 'api')->getJson('/api/v1/notification-templates')->assertOk()->assertJsonCount(12, 'data');
    }

    public function test_manager_can_edit_a_templates_body_and_it_is_audit_logged(): void
    {
        $manager = $this->makeUserWithRole('manager');
        $template = NotificationTemplate::where('key', 'order.status_changed')->where('channel', 'database')->firstOrFail();

        $response = $this->actingAs($manager, 'api')->withCsrf()
            ->patchJson("/api/v1/notification-templates/{$template->id}", ['body' => 'Order {order_number} → {to_status}!']);

        $response->assertOk();
        $this->assertSame('Order {order_number} → {to_status}!', $template->fresh()->body);
        $this->assertDatabaseHas('audit_logs', ['action' => 'notification_template.updated', 'auditable_id' => $template->id]);
    }

    public function test_a_deactivated_template_is_never_rendered(): void
    {
        $template = NotificationTemplate::where('key', 'order.placed')->where('channel', 'database')->firstOrFail();
        $template->update(['is_active' => false]);

        $rendered = app(NotificationTemplateService::class)->render('order.placed', 'database', ['order_number' => 'CC-1']);

        $this->assertNull($rendered);
    }

    public function test_template_placeholders_are_interpolated_from_supplied_data(): void
    {
        $rendered = app(NotificationTemplateService::class)->render('order.status_changed', 'database', [
            'order_number' => 'CC-20260101-ABCDEF',
            'from_status' => 'pending',
            'to_status' => 'confirmed',
        ]);

        $this->assertSame('Your order CC-20260101-ABCDEF is now confirmed.', $rendered['body']);
    }

    public function test_an_order_status_notification_falls_back_to_a_hardcoded_body_when_no_template_exists(): void
    {
        NotificationTemplate::where('key', 'order.status_changed')->delete();

        $customer = Customer::factory()->create();
        $order = Order::factory()->create(['customer_id' => $customer->id, 'order_number' => 'CC-FALLBACK']);

        $customer->user->notify(new OrderStatusChanged($order, 'pending', 'confirmed'));

        $this->assertDatabaseHas('notifications', ['notifiable_id' => $customer->user_id]);
        $record = $customer->user->notifications()->first();
        $this->assertStringContainsString('CC-FALLBACK', $record->data['body']);
    }

    public function test_an_ai_agent_cannot_manage_notification_templates(): void
    {
        $agent = $this->makeAiAgent();

        $this->actingAs($agent, 'api')->getJson('/api/v1/notification-templates')->assertStatus(403);
    }
}
