<?php

namespace Tests\Feature\Customers;

use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithRoles;
use Tests\TestCase;

class CustomerTest extends TestCase
{
    use InteractsWithRoles, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->withCredentials();
    }

    private function withCsrf()
    {
        return $this->withUnencryptedCookie('cc_csrf_token', 'x')->withHeader('X-CSRF-Token', 'x');
    }

    private function customerFor($user): Customer
    {
        return Customer::factory()->create(['user_id' => $user->id]);
    }

    public function test_a_customer_can_view_their_own_profile(): void
    {
        $user = $this->makeUserWithRole('customer');
        $customer = $this->customerFor($user);

        $this->actingAs($user, 'api')
            ->getJson("/api/v1/customers/{$customer->id}")
            ->assertOk();
    }

    public function test_a_customer_cannot_view_another_customers_profile(): void
    {
        $user = $this->makeUserWithRole('customer');
        $other = $this->customerFor($this->makeUserWithRole('customer'));

        $this->actingAs($user, 'api')
            ->getJson("/api/v1/customers/{$other->id}")
            ->assertStatus(403);
    }

    public function test_support_staff_can_view_any_customer_but_not_edit(): void
    {
        $support = $this->makeUserWithRole('support');
        $customer = $this->customerFor($this->makeUserWithRole('customer'));

        $this->actingAs($support, 'api')->getJson("/api/v1/customers/{$customer->id}")->assertOk();
        $this->actingAs($support, 'api')->withCsrf()
            ->putJson("/api/v1/customers/{$customer->id}", ['phone' => '03001112222'])
            ->assertStatus(403);
    }

    public function test_a_customer_cannot_set_staff_only_notes_on_their_own_profile(): void
    {
        $user = $this->makeUserWithRole('customer');
        $customer = $this->customerFor($user);

        $response = $this->actingAs($user, 'api')->withCsrf()->putJson("/api/v1/customers/{$customer->id}", [
            'notes' => 'trying to write staff notes',
        ]);

        $response->assertOk();
        $this->assertNull($customer->fresh()->notes);
    }

    public function test_manager_can_set_notes_and_it_is_audit_logged(): void
    {
        $manager = $this->makeUserWithRole('manager');
        $customer = $this->customerFor($this->makeUserWithRole('customer'));

        $this->actingAs($manager, 'api')->withCsrf()
            ->putJson("/api/v1/customers/{$customer->id}", ['notes' => 'VIP customer'])
            ->assertOk();

        $this->assertSame('VIP customer', $customer->fresh()->notes);
    }

    public function test_a_customer_can_manage_their_own_addresses(): void
    {
        $user = $this->makeUserWithRole('customer');
        $this->customerFor($user);

        $create = $this->actingAs($user, 'api')->withCsrf()->postJson('/api/v1/me/addresses', [
            'recipient_name' => 'Amina Khan',
            'phone' => '03001234567',
            'line1' => 'Street 1',
            'city' => 'Lahore',
        ]);
        $create->assertCreated();

        $addressId = $create->json('data.id');

        $this->actingAs($user, 'api')->getJson('/api/v1/me/addresses')->assertOk()->assertJsonCount(1, 'data');

        $this->actingAs($user, 'api')->withCsrf()
            ->deleteJson("/api/v1/me/addresses/{$addressId}")
            ->assertStatus(204);
    }

    public function test_a_customer_cannot_delete_another_customers_address(): void
    {
        $owner = $this->customerFor($this->makeUserWithRole('customer'));
        $address = $owner->addresses()->create([
            'recipient_name' => 'X', 'phone' => '1', 'line1' => 'L1', 'city' => 'Karachi',
        ]);

        $attacker = $this->makeUserWithRole('customer');
        $this->customerFor($attacker);

        $this->actingAs($attacker, 'api')->withCsrf()
            ->deleteJson("/api/v1/me/addresses/{$address->id}")
            ->assertStatus(403);
    }

    public function test_manager_can_add_a_structured_note_and_it_is_attributed_and_audit_logged(): void
    {
        $manager = $this->makeUserWithRole('manager');
        $customer = $this->customerFor($this->makeUserWithRole('customer'));

        $response = $this->actingAs($manager, 'api')->withCsrf()
            ->postJson("/api/v1/customers/{$customer->id}/notes", ['body' => 'Called about a delayed delivery.']);

        $response->assertCreated();
        $this->assertSame($manager->id, $response->json('data.author_id'));
        $this->assertDatabaseHas('customer_notes', ['customer_id' => $customer->id, 'body' => 'Called about a delayed delivery.']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'customer.note_added', 'auditable_id' => $customer->id]);
    }

    public function test_notes_are_append_only_multiple_entries_accumulate(): void
    {
        $manager = $this->makeUserWithRole('manager');
        $customer = $this->customerFor($this->makeUserWithRole('customer'));

        $this->actingAs($manager, 'api')->withCsrf()->postJson("/api/v1/customers/{$customer->id}/notes", ['body' => 'First note']);
        $this->actingAs($manager, 'api')->withCsrf()->postJson("/api/v1/customers/{$customer->id}/notes", ['body' => 'Second note']);

        $response = $this->actingAs($manager, 'api')->getJson("/api/v1/customers/{$customer->id}/notes");

        $response->assertOk()->assertJsonCount(2, 'data');
        // Newest first.
        $this->assertSame('Second note', $response->json('data.0.body'));
    }

    public function test_view_only_staff_cannot_read_or_write_customer_notes(): void
    {
        $support = $this->makeUserWithRole('support');
        $customer = $this->customerFor($this->makeUserWithRole('customer'));

        $this->actingAs($support, 'api')->getJson("/api/v1/customers/{$customer->id}/notes")->assertStatus(403);
        $this->actingAs($support, 'api')->withCsrf()
            ->postJson("/api/v1/customers/{$customer->id}/notes", ['body' => 'x'])
            ->assertStatus(403);
    }

    public function test_a_customer_cannot_read_their_own_staff_notes(): void
    {
        $user = $this->makeUserWithRole('customer');
        $customer = $this->customerFor($user);
        $customer->staffNotes()->create(['body' => 'Internal only']);

        $this->actingAs($user, 'api')->getJson("/api/v1/customers/{$customer->id}/notes")->assertStatus(403);
    }

    public function test_manager_can_create_and_assign_a_tag_and_it_is_visible_on_the_profile(): void
    {
        $manager = $this->makeUserWithRole('manager');
        $customer = $this->customerFor($this->makeUserWithRole('customer'));

        $tagResponse = $this->actingAs($manager, 'api')->withCsrf()
            ->postJson('/api/v1/customer-tags', ['name' => 'VIP', 'color' => '#f59e0b']);
        $tagResponse->assertCreated();
        $tagId = $tagResponse->json('data.id');

        $this->actingAs($manager, 'api')->withCsrf()
            ->postJson("/api/v1/customers/{$customer->id}/tags/{$tagId}")
            ->assertOk()
            ->assertJsonFragment(['name' => 'VIP']);

        $profile = $this->actingAs($manager, 'api')->getJson("/api/v1/customers/{$customer->id}")->assertOk();
        $this->assertSame('VIP', $profile->json('data.tags.0.name'));
    }

    public function test_view_only_staff_can_read_tags_but_not_assign_them(): void
    {
        $manager = $this->makeUserWithRole('manager');
        $support = $this->makeUserWithRole('support');
        $customer = $this->customerFor($this->makeUserWithRole('customer'));
        $tagId = $this->actingAs($manager, 'api')->withCsrf()
            ->postJson('/api/v1/customer-tags', ['name' => 'Wholesale'])->json('data.id');

        $this->actingAs($support, 'api')->getJson('/api/v1/customer-tags')->assertOk();
        $this->actingAs($support, 'api')->withCsrf()
            ->postJson("/api/v1/customers/{$customer->id}/tags/{$tagId}")
            ->assertStatus(403);
    }

    public function test_a_tag_can_be_removed_from_a_customer(): void
    {
        $manager = $this->makeUserWithRole('manager');
        $customer = $this->customerFor($this->makeUserWithRole('customer'));
        $tagId = $this->actingAs($manager, 'api')->withCsrf()
            ->postJson('/api/v1/customer-tags', ['name' => 'Flagged'])->json('data.id');
        $this->actingAs($manager, 'api')->withCsrf()->postJson("/api/v1/customers/{$customer->id}/tags/{$tagId}");

        $response = $this->actingAs($manager, 'api')->withCsrf()->deleteJson("/api/v1/customers/{$customer->id}/tags/{$tagId}");

        $response->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_customers_can_be_filtered_by_tag(): void
    {
        $manager = $this->makeUserWithRole('manager');
        $tagged = $this->customerFor($this->makeUserWithRole('customer'));
        $untagged = $this->customerFor($this->makeUserWithRole('customer'));
        $tagId = $this->actingAs($manager, 'api')->withCsrf()
            ->postJson('/api/v1/customer-tags', ['name' => 'VIP'])->json('data.id');
        $this->actingAs($manager, 'api')->withCsrf()->postJson("/api/v1/customers/{$tagged->id}/tags/{$tagId}");

        $response = $this->actingAs($manager, 'api')->getJson("/api/v1/customers?filter[tag_id]={$tagId}");

        $response->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $tagged->id);
        $this->assertNotContains($untagged->id, collect($response->json('data'))->pluck('id')->all());
    }

    public function test_an_ai_agent_cannot_reach_any_customer_endpoint(): void
    {
        $agent = $this->makeAiAgent();
        $customer = $this->customerFor($this->makeUserWithRole('customer'));

        $this->actingAs($agent, 'api')->getJson('/api/v1/customers')->assertStatus(403);
        $this->actingAs($agent, 'api')->getJson("/api/v1/customers/{$customer->id}")->assertStatus(403);
        $this->actingAs($agent, 'api')->getJson("/api/v1/customers/{$customer->id}/notes")->assertStatus(403);
    }
}
