<?php

namespace Database\Seeders;

use App\Models\SystemSetting;
use Illuminate\Database\Seeder;

/**
 * Public, storefront-visible settings the chatbot's deterministic lookups
 * (ChatBackendLookupService) and the storefront footer both read - business
 * hours and contact details. `updateOrCreate`-would overwrite an admin's
 * later edit, so this uses `firstOrCreate` like every other reference-data
 * seeder (CategorySeeder, DeliveryRuleSeeder, ...): fills the gap on a
 * fresh database, never clobbers what's already been configured through
 * `/admin/settings`.
 */
class BusinessSettingsSeeder extends Seeder
{
    private const SETTINGS = [
        ['key' => 'business_hours', 'value' => 'Mon-Sat, 9:00 AM - 9:00 PM (closed Sundays)', 'type' => 'string', 'group' => 'business'],
        ['key' => 'contact.phone', 'value' => '+92 300 1234567', 'type' => 'string', 'group' => 'contact'],
        ['key' => 'contact.email', 'value' => 'hello@chococrust.example', 'type' => 'string', 'group' => 'contact'],
        ['key' => 'contact.address', 'value' => 'Khanewal, Punjab, Pakistan', 'type' => 'string', 'group' => 'contact'],
    ];

    public function run(): void
    {
        foreach (self::SETTINGS as $setting) {
            SystemSetting::firstOrCreate(
                ['key' => $setting['key']],
                ['value' => $setting['value'], 'type' => $setting['type'], 'group' => $setting['group'], 'is_public' => true],
            );
        }
    }
}
