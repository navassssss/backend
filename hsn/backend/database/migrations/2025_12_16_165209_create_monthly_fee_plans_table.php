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
        if (!Schema::hasTable('monthly_fee_plans')) {
            Schema::create('monthly_fee_plans', function (Blueprint $table) {
                $table->id();
                $table->foreignId('student_id')->constrained('students')->onDelete('cascade');
                $table->tinyInteger('month'); // 1-12
                $table->smallInteger('year'); // 2024, 2025, etc.
                $table->decimal('payable_amount', 10, 2); // Can be 0.00
                $table->foreignId('set_by')->nullable()->constrained('users')->onDelete('set null');
                $table->string('reason')->nullable(); // "Concession", "Standard fee", etc.
                $table->timestamps();
                
                // Unique constraint: one record per student per month
                $table->unique(['student_id', 'year', 'month'], 'unique_student_month');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('monthly_fee_plans');
    }
};
