<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('class_rooms', function (Blueprint $table) {
            $table->boolean('is_hifz')->default(false)->after('class_teacher_id');
        });
    }

    public function down(): void
    {
        Schema::table('class_rooms', function (Blueprint $table) {
            $table->dropColumn('is_hifz');
        });
    }
};
