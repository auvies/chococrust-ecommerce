<?php

namespace App\Services\Agents;

/**
 * The 8 named future agents from the business brief. Naming these does NOT
 * mean any of them exist yet - "Do not build unnecessary AI agents now" -
 * this is the fixed vocabulary the tool layer's authorization scoping
 * (`AgentToolInterface::allowedAgentTypes()`) is built against, and the
 * only values `php artisan agent:provision` will accept when a specific
 * agent identity is actually provisioned in a later phase.
 *
 * A plain string-constant list, not a native DB enum, matching the
 * `payments.method`/`users.agent_type` precedent - the set is expected to
 * grow without a schema migration.
 */
final class AgentType
{
    public const SALES = 'sales_agent';

    public const CUSTOMER_SUPPORT = 'customer_support_agent';

    public const ORDER = 'order_agent';

    public const PAYMENT = 'payment_agent';

    public const INVENTORY = 'inventory_agent';

    public const MARKETING = 'marketing_agent';

    public const DISPATCH = 'dispatch_agent';

    public const ANALYTICS = 'analytics_agent';

    public const ALL = [
        self::SALES, self::CUSTOMER_SUPPORT, self::ORDER, self::PAYMENT,
        self::INVENTORY, self::MARKETING, self::DISPATCH, self::ANALYTICS,
    ];
}
