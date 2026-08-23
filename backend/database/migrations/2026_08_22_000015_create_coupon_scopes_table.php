<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Polymorphic scoping: a coupon with no rows here applies store-wide;
        // rows here restrict it to specific categories or products without
        // needing a separate pivot table per scopable type.
        Schema::create('coupon_scopes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $table->string('scopable_type');
            $table->unsignedBigInteger('scopable_id');
            $table->timestamps();

            $table->unique(['coupon_id', 'scopable_type', 'scopable_id'], 'ux_coupon_scopes_target');
            $table->index(['scopable_type', 'scopable_id'], 'ix_coupon_scopes_scopable');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupon_scopes');
    }
};
