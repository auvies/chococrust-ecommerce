<?php

namespace Tests\Feature\Notifications;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\InteractsWithRoles;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use InteractsWithRoles, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->withCredentials();
    }

    private function seedNotification(User $user): string
    {
        $id = (string) Str::uuid();
        $user->notifications()->create([
            'id' => $id,
            'type' => 'App\\Notifications\\Test',
            'data' => ['message' => 'Your order was confirmed'],
        ]);

        return $id;
    }

    public function test_a_user_only_sees_their_own_notifications(): void
    {
        $user = $this->makeUserWithRole('customer');
        $other = $this->makeUserWithRole('customer');
        $this->seedNotification($user);
        $this->seedNotification($other);

        $response = $this->actingAs($user, 'api')->getJson('/api/v1/notifications');

        $response->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_marking_a_notification_read(): void
    {
        $user = $this->makeUserWithRole('customer');
        $id = $this->seedNotification($user);

        $response = $this->actingAs($user, 'api')
            ->withUnencryptedCookie('cc_csrf_token', 'x')->withHeader('X-CSRF-Token', 'x')
            ->patchJson("/api/v1/notifications/{$id}/read");

        $response->assertOk();
        $this->assertNotNull($user->notifications()->find($id)->read_at);
    }

    public function test_a_user_cannot_mark_another_users_notification_read(): void
    {
        $user = $this->makeUserWithRole('customer');
        $other = $this->makeUserWithRole('customer');
        $id = $this->seedNotification($other);

        $this->actingAs($user, 'api')
            ->withUnencryptedCookie('cc_csrf_token', 'x')->withHeader('X-CSRF-Token', 'x')
            ->patchJson("/api/v1/notifications/{$id}/read")
            ->assertStatus(404);
    }
}
