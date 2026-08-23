<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Converts orders.status, payments.status, and inventory_reservations.status
 * from native DB enums to plain indexed strings, matching the precedent
 * already set by payments.method (see its migration's own comment: "the
 * gateway lineup ... shouldn't require a migration to add"). This phase
 * alone needs 'ready'/'completed' order statuses, five new payment statuses
 * (COD_PENDING/COD_CONFIRMED/COD_COLLECTED/PAID/FAILED plus the existing
 * REFUNDED), and a 'fulfilled' reservation status - a native enum would
 * need a schema migration (via doctrine/dbal, which isn't installed in this
 * sandbox) every time that vocabulary grows again. Validation moves
 * entirely to the application layer (Form Requests, OrderService's own
 * transition graph, PaymentService), which is also portable across every
 * database this project might run on (CLAUDE.md §22).
 *
 * The index has to be dropped before the column (SQLite refuses to drop a
 * column that's part of an index) and re-created after the column is back -
 * four small `Schema::table` calls per column instead of one `->change()`,
 * specifically to avoid needing doctrine/dbal.
 *
 * No live database exists yet (see README), so there's no data migration to
 * worry about - a string column accepts every value the old enum did.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', fn (Blueprint $table) => $table->dropIndex(['status']));
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn('status'));
        Schema::table('orders', fn (Blueprint $table) => $table->string('status')->default('pending'));
        Schema::table('orders', fn (Blueprint $table) => $table->index('status'));

        Schema::table('payments', fn (Blueprint $table) => $table->dropIndex(['status']));
        Schema::table('payments', fn (Blueprint $table) => $table->dropColumn('status'));
        Schema::table('payments', fn (Blueprint $table) => $table->string('status')->default('pending'));
        Schema::table('payments', fn (Blueprint $table) => $table->index('status'));

        Schema::table('inventory_reservations', fn (Blueprint $table) => $table->dropIndex(['product_variant_id', 'status']));
        Schema::table('inventory_reservations', fn (Blueprint $table) => $table->dropColumn('status'));
        Schema::table('inventory_reservations', fn (Blueprint $table) => $table->string('status')->default('pending'));
        Schema::table('inventory_reservations', fn (Blueprint $table) => $table->index(['product_variant_id', 'status']));

        Schema::table('deliveries', function (Blueprint $table) {
            // Distinct from the delivery status itself (which already
            // covers "failed"/"returned") - these capture *why* and *how
            // many times*, without growing the status list for every new
            // failure reason (customer refused, address not found, ...).
            $table->unsignedInteger('delivery_attempts')->default(0)->after('status');
            $table->string('failure_reason')->nullable()->after('delivery_attempts');
        });
    }

    public function down(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->dropColumn(['delivery_attempts', 'failure_reason']);
        });

        Schema::table('inventory_reservations', fn (Blueprint $table) => $table->dropIndex(['product_variant_id', 'status']));
        Schema::table('inventory_reservations', fn (Blueprint $table) => $table->dropColumn('status'));
        Schema::table('inventory_reservations', fn (Blueprint $table) => $table->enum('status', ['pending', 'committed', 'released', 'expired'])->default('pending'));
        Schema::table('inventory_reservations', fn (Blueprint $table) => $table->index(['product_variant_id', 'status']));

        Schema::table('payments', fn (Blueprint $table) => $table->dropIndex(['status']));
        Schema::table('payments', fn (Blueprint $table) => $table->dropColumn('status'));
        Schema::table('payments', fn (Blueprint $table) => $table->enum('status', [
            'pending', 'authorized', 'paid', 'partially_refunded', 'refunded', 'failed', 'cancelled',
        ])->default('pending'));
        Schema::table('payments', fn (Blueprint $table) => $table->index('status'));

        Schema::table('orders', fn (Blueprint $table) => $table->dropIndex(['status']));
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn('status'));
        Schema::table('orders', fn (Blueprint $table) => $table->enum('status', [
            'pending', 'confirmed', 'processing', 'dispatched', 'delivered', 'cancelled', 'refunded',
        ])->default('pending'));
        Schema::table('orders', fn (Blueprint $table) => $table->index('status'));
    }
};
