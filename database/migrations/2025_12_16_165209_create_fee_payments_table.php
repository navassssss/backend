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
        Schema::create('fee_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->onDelete('cascade');
            $table->decimal('paid_amount', 10, 2);
            $table->date('payment_date');
            $table->boolean('receipt_issued')->default(false);
            $table->foreignId('entered_by')->constrained('users')->onDelete('restrict');
            $table->text('remarks')->nullable();
            $table->timestamps();
            
            // Indexes for efficient queries
            $table->index(['student_id', 'payment_date'], 'idx_student_date');
            $table->index('payment_date', 'idx_payment_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fee_payments');
    }
};
