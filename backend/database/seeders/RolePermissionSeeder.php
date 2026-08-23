<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Baseline roles and permissions (CLAUDE.md §8, extended per the Phase 03
 * brief). No user accounts or credentials are seeded here - see
 * `php artisan role:assign` (App\Console\Commands\AssignRole) for
 * provisioning the first super_admin out of band, so no default admin
 * password ever ships in source control.
 *
 * Least privilege, deliberately:
 * - `manager` gets full operational access but NOT roles.manage or
 *   settings.manage - that split (system config / role assignment stays
 *   super_admin-only) is a direct CLAUDE.md §8 requirement.
 * - `ai_agent` gets exactly one permission, ai.tools.use, and nothing
 *   else - it is never combined with any staff role
 *   ("AI agents must NOT be normal administrators").
 */
class RolePermissionSeeder extends Seeder
{
    private const PERMISSIONS = [
        'products.view' => 'View products',
        'products.manage' => 'Create/update/delete/publish products',
        'categories.manage' => 'Manage category tree',
        'inventory.manage' => 'Adjust stock levels',
        'orders.view' => 'View orders',
        'orders.manage' => 'Transition order lifecycle status',
        'orders.refund' => 'Issue refunds',
        'payments.view' => 'View payment records',
        'payments.manage' => 'Change payment status',
        'cod.collect' => 'Mark a COD order as cash-collected (rider-level)',
        'cod.manage' => 'Verify/reconcile COD collections and manage COD records generally',
        'deliveries.view_own' => 'View own assigned deliveries',
        'deliveries.manage_own' => 'Update status of own assigned deliveries',
        'deliveries.manage_all' => 'Assign and manage any delivery',
        'customers.view' => 'View customer accounts',
        'customers.manage' => 'Edit customer accounts, staff notes, and tags',
        'coupons.manage' => 'Manage discount coupons',
        'reviews.moderate' => 'Approve/reject product reviews',
        'content.manage' => 'Manage hero banners, homepage sections, themes, SEO metadata',
        'notifications.manage' => 'Edit order/payment/delivery notification templates',
        'chat.view' => 'View chatbot conversations',
        'chat.respond' => 'Respond to chatbot conversations as staff',
        'staff.manage' => 'Create/edit/deactivate staff accounts',
        'roles.manage' => 'Assign roles and permissions (privilege escalation)',
        'settings.manage' => 'Manage system configuration/integrations',
        'audit.view' => 'View the audit log',
        'analytics.view' => 'View operational analytics/reporting',
        'ai.tools.use' => 'Invoke the AI agent allow-listed tool set',
        'agent_actions.manage' => 'Review and approve/reject pending high-risk agent tool calls',
    ];

    private const ROLES_TEMPLATE = [
        'customer' => [
            'name' => 'Customer',
            'description' => 'Own account, own orders, chatbot access.',
            'permissions' => [],
        ],
        'support' => [
            'name' => 'Support',
            'description' => 'Read customer orders/chats for support; no financial or destructive actions.',
            'permissions' => ['customers.view', 'orders.view', 'chat.view', 'chat.respond'],
        ],
        'order_manager' => [
            'name' => 'Order Manager',
            'description' => 'Manages the order lifecycle: confirm, prepare, dispatch, mark delivered/COD collected.',
            'permissions' => ['orders.view', 'orders.manage', 'cod.manage', 'deliveries.manage_all', 'customers.view'],
        ],
        'delivery_rider' => [
            'name' => 'Delivery Rider',
            'description' => 'Views and updates status only for orders assigned to them; marks COD cash collected on delivery.',
            'permissions' => ['deliveries.view_own', 'deliveries.manage_own', 'cod.collect'],
        ],
        'content_manager' => [
            'name' => 'Content Manager',
            'description' => 'Manages the catalog and storefront content: products, categories, banners, coupons, reviews.',
            'permissions' => [
                'products.view', 'products.manage', 'categories.manage',
                'content.manage', 'coupons.manage', 'reviews.moderate',
            ],
        ],
        'manager' => [
            'name' => 'Manager',
            'description' => 'Full operational access: products, orders, payments, customers, refunds. Not system configuration or role assignment.',
            'permissions' => [
                'products.view', 'products.manage', 'categories.manage', 'inventory.manage',
                'orders.view', 'orders.manage', 'orders.refund',
                'payments.view', 'payments.manage', 'cod.manage', 'deliveries.manage_all',
                'customers.view', 'customers.manage', 'coupons.manage', 'reviews.moderate',
                'content.manage', 'notifications.manage', 'staff.manage', 'audit.view', 'analytics.view',
                'agent_actions.manage',
            ],
        ],
        'super_admin' => [
            'name' => 'Super Admin',
            'description' => 'Manager + system configuration, role assignment, integrations/secrets management.',
            'permissions' => null, // filled in with every permission slug at runtime, see roles()
        ],
        'ai_agent' => [
            'name' => 'AI Agent',
            'description' => 'A non-human, allow-listed identity for the AI chatbot/agents. Never combined with a staff role.',
            'permissions' => ['ai.tools.use'],
        ],
    ];

    /**
     * self::ROLES_TEMPLATE can't compute super_admin's permission list at
     * compile time (PHP class constants only allow constant expressions,
     * and array_keys(self::PERMISSIONS) isn't one) - so it's filled in
     * here instead, at call time.
     *
     * super_admin gets every *administrative* permission but deliberately
     * NOT ai.tools.use: that permission is the AI agent identity's own
     * lane, never bundled into a human role even the most privileged one -
     * "AI agents must NOT be normal administrators" cuts both ways, no
     * staff account should incidentally gain AI tool-invocation rights
     * just because it has "everything else".
     */
    private static function roles(): array
    {
        $administrativePermissions = array_values(array_diff(array_keys(self::PERMISSIONS), ['ai.tools.use']));

        return array_replace_recursive(self::ROLES_TEMPLATE, [
            'super_admin' => ['permissions' => $administrativePermissions],
        ]);
    }

    public function run(): void
    {
        $permissions = collect(self::PERMISSIONS)->map(
            fn (string $description, string $slug) => Permission::updateOrCreate(
                ['slug' => $slug],
                ['name' => $description, 'description' => $description],
            )
        );

        foreach (self::roles() as $slug => $definition) {
            $role = Role::updateOrCreate(
                ['slug' => $slug],
                ['name' => $definition['name'], 'description' => $definition['description']],
            );

            $permissionIds = $permissions->only($definition['permissions'])->pluck('id');
            $role->permissions()->sync($permissionIds);
        }
    }
}
