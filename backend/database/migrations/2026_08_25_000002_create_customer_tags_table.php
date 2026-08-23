<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Staff-defined labels ("VIP", "Wholesale", "Flagged") - a small,
        // admin-managed reference table, not a free-text field, so tags stay
        // consistent and filterable across the customer base.
        Schema::create('customer_tags', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('color', 20)->nullable();
            $table->timestamps();
        });

        // Many-to-many, but tracked as its own append-only-ish assignment
        // table (not a bare pivot) so "who tagged this customer, and when"
        // is recorded - the same reasoning as `inventory_movements` over a
        // plain pivot (Phase 09 ADR 0009).
        Schema::create('customer_tag_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_tag_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['customer_id', 'customer_tag_id'], 'ux_customer_tag_assignments');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_tag_assignments');
        Schema::dropIfExists('customer_tags');
    }
};
