<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_rules', function (Blueprint $table) {
            $table->id();
            // scope decides which of category_id/product_id (if either) is
            // populated: 'global' -> both null, 'category' -> category_id
            // set, 'product' -> product_id set. Enforced at the model layer
            // (see DeliveryRule::boot) since a portable cross-column CHECK
            // isn't worth the DB-specific SQL it would take.
            $table->enum('scope', ['global', 'category', 'product']);
            $table->foreignId('category_id')->nullable()->constrained('categories')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->cascadeOnDelete();
            $table->enum('delivery_type', ['local', 'nationwide', 'both'])->default('both');
            $table->boolean('is_deliverable')->default(true);
            $table->decimal('min_order_amount', 10, 2)->nullable();
            $table->decimal('flat_fee', 10, 2)->nullable();
            // Higher priority wins when more than one rule could apply
            // (e.g. a product-level rule overriding its category's rule).
            $table->integer('priority')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(
                ['scope', 'category_id', 'product_id', 'delivery_type'],
                'ux_delivery_rules_scope_target'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_rules');
    }
};
