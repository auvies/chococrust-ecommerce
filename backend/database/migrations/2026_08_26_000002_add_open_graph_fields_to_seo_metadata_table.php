<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Open Graph copy can legitimately differ from the plain meta
        // title/description (more casual, written for a social share
        // preview rather than a search result) - previously only
        // `og_image_url` existed, silently reusing meta_title/description
        // for og:title/og:description with no way to override either.
        Schema::table('seo_metadata', function (Blueprint $table) {
            $table->string('og_title')->nullable()->after('meta_keywords');
            $table->string('og_description')->nullable()->after('og_title');
        });
    }

    public function down(): void
    {
        Schema::table('seo_metadata', function (Blueprint $table) {
            $table->dropColumn(['og_title', 'og_description']);
        });
    }
};
