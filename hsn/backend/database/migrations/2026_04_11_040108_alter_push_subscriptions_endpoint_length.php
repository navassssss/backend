<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('push_subscriptions', function (Blueprint $table) {
            // Drop unique index first because MySQL cannot unique index a full TEXT/BLOB
            $table->dropUnique('push_subscriptions_endpoint_unique');
            $table->text('endpoint')->change();
            $table->text('p256dh_key')->nullable()->change();
            $table->text('auth_token')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('push_subscriptions', function (Blueprint $table) {
            $table->string('endpoint', 255)->change();
            $table->unique('endpoint');
            $table->string('p256dh_key')->nullable()->change();
            $table->string('auth_token')->nullable()->change();
        });
    }
};
