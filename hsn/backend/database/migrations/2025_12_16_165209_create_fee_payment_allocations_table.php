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
        if (!Schema::hasTable('fee_payment_allocations')) {
            Schema::create('fee_payment_allocations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('fee_payment_id')->constrained('fee_payments')->onDelete('cascade');
                $table->foreignId('student_id')->constrained('students')->onDelete('cascade');
                $table->tinyInteger('month'); // 1-12
                $table->smallInteger('year'); // 2024, 2025, etc.
                $table->decimal('allocated_amount', 10, 2);
                $table->timestamps();
                
                // Indexes for efficient queries
                $table->index('fee_payment_id', 'idx_payment');
                $table->index(['student_id', 'year', 'month'], 'idx_student_month');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fee_payment_allocations');
    }
};
