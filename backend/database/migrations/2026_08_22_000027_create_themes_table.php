<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('themes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            // Colors/fonts/tokens - shape is intentionally open-ended.
            $table->json('config');
            // "Only one active theme" is enforced at the model layer
            // (Theme::boot), not a DB constraint - a portable partial
            // unique index isn't worth the DB-specific SQL it would take.
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('themes');
    }
};
