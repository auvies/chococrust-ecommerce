<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained()->cascadeOnDelete();
            $table->enum('type', ['local', 'nationwide']);
            $table->string('courier_name')->nullable();
            // Only set for local deliveries handled by an in-house rider.
            $table->foreignId('rider_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('tracking_number')->nullable()->unique();
            $table->enum('status', [
                'pending', 'assigned', 'picked_up', 'in_transit',
                'out_for_delivery', 'delivered', 'failed', 'returned',
            ])->default('pending');
            $table->foreignId('address_id')->nullable()->constrained('addresses')->nullOnDelete();
            $table->decimal('delivery_fee', 10, 2)->default(0);
            $table->timestamp('estimated_delivery_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deliveries');
    }
};
