<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\InteractsWithRoles;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use InteractsWithRoles, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    public function test_a_new_customer_can_register(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Amina Khan',
            'email' => 'amina@example.com',
            'password' => 'Str0ngPass!',
            'password_confirmation' => 'Str0ngPass!',
        ]);

        $response->assertCreated();
        $response->assertCookie('cc_access_token');
        $response->assertCookie('cc_refresh_token');
        $response->assertCookie('cc_csrf_token');

        $user = User::where('email', 'amina@example.com')->firstOrFail();
        $this->assertTrue($user->hasRole('customer'));
        $this->assertNotNull($user->customer, 'A customer profile row must be created.');
        $this->assertTrue(Hash::check('Str0ngPass!', $user->password), 'Password must be hashed, not stored in plaintext.');
    }

    public function test_registration_response_never_contains_the_password_hash(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Amina Khan',
            'email' => 'amina@example.com',
            'password' => 'Str0ngPass!',
            'password_confirmation' => 'Str0ngPass!',
        ]);

        $response->assertJsonMissing(['password']);
        $this->assertStringNotContainsString('Str0ngPass', $response->getContent());
    }

    public function test_registration_rejects_a_weak_password(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Amina Khan',
            'email' => 'amina@example.com',
            'password' => 'short',
            'password_confirmation' => 'short',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('password');
    }

    public function test_registration_rejects_a_duplicate_email(): void
    {
        User::factory()->create(['email' => 'amina@example.com']);

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Amina Khan',
            'email' => 'amina@example.com',
            'password' => 'Str0ngPass!',
            'password_confirmation' => 'Str0ngPass!',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('email');
    }

    public function test_registration_rejects_unknown_fields_silently_by_ignoring_them(): void
    {
        // CLAUDE.md §13: extra fields are never trusted into the model -
        // an attacker can't smuggle e.g. is_active=false or type=ai_agent
        // through the registration payload.
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Amina Khan',
            'email' => 'amina@example.com',
            'password' => 'Str0ngPass!',
            'password_confirmation' => 'Str0ngPass!',
            'type' => 'ai_agent',
            'is_active' => false,
        ]);

        $response->assertCreated();
        $user = User::where('email', 'amina@example.com')->firstOrFail();
        $this->assertSame('human', $user->type);
        $this->assertTrue($user->is_active);
    }
}
