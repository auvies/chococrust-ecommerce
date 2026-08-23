<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Payment Methods
    |--------------------------------------------------------------------------
    |
    | The allow-listed set of `payments.method` values a customer may select
    | at checkout. A plain config list, not a database enum (see the
    | payments migration's own comment) - adding a future gateway is a
    | one-line change here, never a schema migration.
    |
    | Only 'cod' is actually processed end-to-end today. 'easypaisa' and
    | 'jazzcash' are accepted and modeled (a Payment row is created with
    | status='pending' and gateway=<method>), but no live redirect/webhook
    | integration exists yet - there are no gateway API keys configured
    | (CLAUDE.md §6), and building the webhook signature-verification flow
    | CLAUDE.md §5 requires is deliberately out of scope until a gateway
    | account actually exists. This is the same "prepared, not built"
    | posture as MFA (see ADR 0003) - the data model and status machine are
    | ready; the live integration is a following phase's work.
    |
    */

    'methods' => [
        'cod',
        'easypaisa',
        'jazzcash',
    ],

    /*
    |--------------------------------------------------------------------------
    | Cash-on-Delivery Methods
    |--------------------------------------------------------------------------
    |
    | Which of the methods above are cash-on-delivery (no gateway
    | involved at all) - this is what decides whether an order gets a
    | `cod_records` row and starts its payment life at 'cod_pending'
    | instead of the generic 'pending'.
    |
    */

    'cod_methods' => [
        'cod',
    ],

    /*
    |--------------------------------------------------------------------------
    | Payment Statuses
    |--------------------------------------------------------------------------
    |
    | The full set of valid `payments.status` values, kept in one place so
    | Form Requests/services validate against the same list rather than
    | duplicating string literals. Order status and payment status are
    | tracked completely independently (CLAUDE.md's own "orders.status vs
    | payments.status vs cod_records.status are three separate state
    | machines" design) - nothing here ever changes `orders.status`.
    |
    | - pending          Generic: a non-COD payment awaiting gateway confirmation.
    | - cod_pending       COD order placed, awaiting staff confirmation.
    | - cod_confirmed     Staff confirmed the COD order will proceed.
    | - cod_collected     A rider collected cash on delivery.
    | - paid              Fully settled - online gateway success, or COD cash verified/deposited.
    | - failed            Payment failed (gateway declined, COD delivery failed with nothing collected).
    | - partially_refunded / refunded  Some or all of the amount has been returned.
    | - cancelled         The order was cancelled before payment ever completed.
    |
    */

    'statuses' => [
        'pending',
        'cod_pending',
        'cod_confirmed',
        'cod_collected',
        'paid',
        'failed',
        'partially_refunded',
        'refunded',
        'cancelled',
    ],
];
