<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add assignment_scope to subjects
        Schema::table('subjects', function (Blueprint $table) {
            $table->enum('assignment_scope', ['full_class', 'selected_students'])
                  ->default('full_class')
                  ->after('is_locked');
        });

        // Pivot table for selected-student assignments
        Schema::create('subject_students', function (Blueprint $table) {
            $table->unsignedBigInteger('subject_id');
            $table->unsignedBigInteger('student_id');
            $table->primary(['subject_id', 'student_id']);
            $table->foreign('subject_id')->references('id')->on('subjects')->onDelete('cascade');
            $table->foreign('student_id')->references('id')->on('students')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subject_students');

        Schema::table('subjects', function (Blueprint $table) {
            $table->dropColumn('assignment_scope');
        });
    }
};
