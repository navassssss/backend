<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medical_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reported_by')->constrained('users')->cascadeOnDelete();
            $table->string('illness_name');
            $table->timestamp('reported_at');
            $table->boolean('went_to_doctor')->default(false);
            $table->text('notes')->nullable();

            // Resolution
            $table->timestamp('recovered_at')->nullable();
            $table->foreignId('recovered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('sent_home_at')->nullable();
            $table->foreignId('sent_home_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index('student_id');
            $table->index('reported_at');
        });

        // Add manage_medical permission
        DB::table('permissions')->insert([
            'name'       => 'manage_medical',
            'label'      => 'Manage Medical Records',
            'module'     => 'Health',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('medical_records');
        DB::table('permissions')->where('name', 'manage_medical')->delete();
    }
};
