<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique();
            // Nullable: a guest checkout order has no customer profile at
            // all and relies purely on the contact_* snapshot below.
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();

            // Order lifecycle only. Payment status lives entirely on the
            // payments table - this column never conflates the two.
            $table->enum('status', [
                'pending', 'confirmed', 'processing', 'dispatched',
                'delivered', 'cancelled', 'refunded',
            ])->default('pending');

            $table->char('currency', 3)->default('PKR');
            $table->decimal('subtotal', 12, 2);
            $table->decimal('discount_total', 12, 2)->default(0);
            $table->decimal('delivery_fee', 12, 2)->default(0);
            $table->decimal('tax_total', 12, 2)->default(0);
            $table->decimal('total', 12, 2);

            $table->enum('delivery_type', ['local', 'nationwide']);
            $table->foreignId('coupon_id')->nullable()->constrained('coupons')->nullOnDelete();

            // Addresses are referenced AND snapshotted: the FK supports
            // normalized lookups, the JSON snapshot keeps the order's
            // historical record accurate even if the saved address is later
            // edited or removed.
            $table->foreignId('shipping_address_id')->nullable()->constrained('addresses')->nullOnDelete();
            $table->json('shipping_address_snapshot')->nullable();
            $table->foreignId('billing_address_id')->nullable()->constrained('addresses')->nullOnDelete();
            $table->json('billing_address_snapshot')->nullable();

            // Contact snapshot, independent of customer_id, so the order
            // remains legible even for guests or if a profile changes later.
            $table->string('contact_name');
            $table->string('contact_phone');
            $table->string('contact_email')->nullable();

            $table->text('notes')->nullable();
            $table->timestamp('placed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
