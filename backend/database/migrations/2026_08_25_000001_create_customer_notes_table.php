<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Append-only, structured staff notes on a customer - distinct from
        // the single overwritable `customers.notes` text field (Phase 02):
        // that field is still a quick-glance summary, this table is the
        // real, timestamped, multi-author record ("who said what, when")
        // that a single mutable field can never be, and it's never shown to
        // the customer themselves (CustomerNoteController is staff-only).
        Schema::create('customer_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('body');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_notes');
    }
};
