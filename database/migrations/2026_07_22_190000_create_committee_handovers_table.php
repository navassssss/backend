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
        Schema::create('committee_handovers', function (Blueprint $table) {
            $table->id();
            $table->date('handover_date');
            $table->decimal('amount', 12, 2);
            $table->string('recipient_name'); // e.g., 'Treasurer', 'Secretary', 'Committee Account'
            $table->enum('payment_mode', ['cash', 'bank_transfer', 'cheque', 'upi'])->default('cash');
            $table->string('reference_number')->nullable(); // Receipt or Bank Ref #
            $table->foreignId('handed_over_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index(['handover_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('committee_handovers');
    }
};
