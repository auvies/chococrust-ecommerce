<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Additive, nullable columns only - no destructive change, safe to run
 * against a populated database (CLAUDE.md §11).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_rules', function (Blueprint $table) {
            // Restricts a 'local' rule to specific cities/areas (case-
            // insensitive match against the shipping address) - null means
            // no geographic restriction. This is what makes "local delivery
            // only in Khanewal and surrounding areas" backend-configurable
            // data instead of a hardcoded check.
            $table->json('local_areas')->nullable()->after('flat_fee');
            // Informational ETA surfaced to the customer (e.g. "~2 hours"
            // for fresh local orders) - configuration, not a hard constraint.
            $table->unsignedInteger('estimated_minutes')->nullable()->after('local_areas');
        });

        Schema::table('order_items', function (Blueprint $table) {
            // Free-text customization per line item (e.g. a cake message),
            // distinct from variant selection (size/flavor).
            $table->string('customization_note', 500)->nullable()->after('quantity');
        });

        Schema::table('orders', function (Blueprint $table) {
            // Snapshotted from the delivery rule matched at order-creation
            // time, so the confirmation/tracking view can keep showing the
            // original ETA even if the rule is edited later.
            $table->unsignedInteger('estimated_delivery_minutes')->nullable()->after('delivery_type');
        });
    }

    public function down(): void
    {
        Schema::table('delivery_rules', function (Blueprint $table) {
            $table->dropColumn(['local_areas', 'estimated_minutes']);
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('customization_note');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('estimated_delivery_minutes');
        });
    }
};
