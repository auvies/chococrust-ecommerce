<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Reference data only (roles/permissions, initial category tree,
        // default homepage layout, delivery configuration) - safe to run in
        // every environment, including production. Idempotent
        // (updateOrCreate/firstOrCreate).
        $this->call(RolePermissionSeeder::class);
        $this->call(CategorySeeder::class);
        $this->call(HomepageSectionSeeder::class);
        // Must run after CategorySeeder - it looks up fresh categories by slug.
        $this->call(DeliveryRuleSeeder::class);
        $this->call(NotificationTemplateSeeder::class);
        $this->call(ThemeSeeder::class);
        $this->call(BusinessSettingsSeeder::class);

        // Sample account, local development only. No default admin/staff
        // account is ever seeded anywhere (CLAUDE.md §6) - provisioning
        // the first super_admin is a deliberate, out-of-band action via
        // `php artisan role:assign` (see App\Console\Commands\AssignRole).
        if (app()->environment('local', 'testing')) {
            User::factory()->create([
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);
        }
    }
}
