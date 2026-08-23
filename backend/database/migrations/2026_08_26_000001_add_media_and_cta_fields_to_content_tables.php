<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hero_banners', function (Blueprint $table) {
            // Desktop/mobile need genuinely different crops, not just one
            // image scaled down - `image_url` (existing) is now "desktop."
            $table->string('mobile_image_url')->nullable()->after('image_url');
            $table->string('alt_text')->nullable()->after('mobile_image_url');
            // The CTA is a labeled button, not just a bare link - `link_url`
            // (existing) is where it goes, this is what it says.
            $table->string('cta_text')->nullable()->after('link_url');
        });

        // `image_url` was NOT NULL - a banner is now typically created
        // text-first, then given its image via the dedicated upload
        // endpoint (HeroBannerController::uploadImage()), so creation can
        // no longer require it up front. Dropped and re-added rather than
        // `->nullable()->change()` (needs doctrine/dbal, not installed in
        // this sandbox - the exact same constraint Phase 09/ADR 0009 hit
        // converting the status columns).
        Schema::table('hero_banners', function (Blueprint $table) {
            $table->dropColumn('image_url');
        });
        Schema::table('hero_banners', function (Blueprint $table) {
            $table->string('image_url')->nullable()->after('subtitle');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->string('alt_text')->nullable()->after('image_url');
        });
    }

    public function down(): void
    {
        Schema::table('hero_banners', function (Blueprint $table) {
            $table->dropColumn(['mobile_image_url', 'alt_text', 'cta_text']);
        });

        Schema::table('hero_banners', function (Blueprint $table) {
            $table->dropColumn('image_url');
        });
        Schema::table('hero_banners', function (Blueprint $table) {
            $table->string('image_url')->after('subtitle');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('alt_text');
        });
    }
};
