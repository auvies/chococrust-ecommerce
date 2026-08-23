<?php

namespace Tests\Feature\Chat;

use App\Models\ChatConversation;
use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithRoles;
use Tests\TestCase;

class ChatTest extends TestCase
{
    use InteractsWithRoles, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->withCredentials();
    }

    public function test_a_customer_can_start_a_conversation_and_send_a_message(): void
    {
        $user = $this->makeUserWithRole('customer');
        Customer::factory()->create(['user_id' => $user->id]);

        $create = $this->actingAs($user, 'api')
            ->withUnencryptedCookie('cc_csrf_token', 'x')->withHeader('X-CSRF-Token', 'x')
            ->postJson('/api/v1/chat/conversations');
        $create->assertCreated();

        $id = $create->json('data.id');

        $message = $this->actingAs($user, 'api')
            ->withUnencryptedCookie('cc_csrf_token', 'x')->withHeader('X-CSRF-Token', 'x')
            ->postJson("/api/v1/chat/conversations/{$id}/messages", ['content' => 'Do you deliver to Lahore?']);

        $message->assertCreated();
        $message->assertJsonPath('data.message.sender_type', 'customer');
    }

    public function test_a_customer_cannot_view_another_customers_conversation(): void
    {
        $conversation = ChatConversation::factory()->create();
        $intruder = $this->makeUserWithRole('customer');
        Customer::factory()->create(['user_id' => $intruder->id]);

        $this->actingAs($intruder, 'api')
            ->getJson("/api/v1/chat/conversations/{$conversation->id}")
            ->assertStatus(403);
    }

    public function test_support_staff_can_respond_to_any_conversation(): void
    {
        $conversation = ChatConversation::factory()->create();
        $support = $this->makeUserWithRole('support');

        $response = $this->actingAs($support, 'api')
            ->withUnencryptedCookie('cc_csrf_token', 'x')->withHeader('X-CSRF-Token', 'x')
            ->postJson("/api/v1/chat/conversations/{$conversation->id}/messages", ['content' => 'Yes, we deliver nationwide.']);

        $response->assertCreated();
        $response->assertJsonPath('data.message.sender_type', 'staff');
        // Staff messages never trigger a bot reply - they're already handling it.
        $response->assertJsonPath('data.reply', null);
    }

    public function test_a_customer_cannot_respond_to_someone_elses_conversation(): void
    {
        $conversation = ChatConversation::factory()->create();
        $intruder = $this->makeUserWithRole('customer');
        Customer::factory()->create(['user_id' => $intruder->id]);

        $this->actingAs($intruder, 'api')
            ->withUnencryptedCookie('cc_csrf_token', 'x')->withHeader('X-CSRF-Token', 'x')
            ->postJson("/api/v1/chat/conversations/{$conversation->id}/messages", ['content' => 'hi'])
            ->assertStatus(403);
    }
}
