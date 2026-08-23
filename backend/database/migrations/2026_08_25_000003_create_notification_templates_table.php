<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per (event key, channel) - e.g. ('order.status_changed',
        // 'mail'). `body` supports `{placeholder}` tokens interpolated by
        // NotificationTemplateService against the data each Notification
        // class supplies. Admin-editable content, but the set of valid keys
        // is fixed by the notification classes that reference them (see
        // NotificationTemplateController - index/update only, no ad hoc
        // create via the API).
        Schema::create('notification_templates', function (Blueprint $table) {
            $table->id();
            $table->string('key');
            $table->string('channel');
            $table->string('subject')->nullable();
            $table->text('body');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['key', 'channel'], 'ux_notification_templates_key_channel');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_templates');
    }
};
