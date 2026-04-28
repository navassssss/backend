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
        Schema::table('announcements', function (Blueprint $table) {
            $table->index(['created_by', 'audience_type', 'target_type'], 'idx_announcements_creation_targets');
        });

        Schema::table('attendances', function (Blueprint $table) {
            $table->index(['class_id', 'date'], 'idx_attendances_class_date');
            $table->index(['date', 'class_id'], 'idx_attendances_date_class');
        });

        Schema::table('attendance_records', function (Blueprint $table) {
            $table->index(['student_id', 'status'], 'idx_attendance_records_student_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tables', function (Blueprint $table) {
            //
        });
    }
};
