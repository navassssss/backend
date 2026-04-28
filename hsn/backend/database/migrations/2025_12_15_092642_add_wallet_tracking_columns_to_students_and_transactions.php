<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->decimal('opening_balance', 10, 2)->default(0)->after('wallet_balance');
            $table->integer('last_processed_row')->nullable()->after('opening_balance');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->integer('sheet_row_number')->nullable()->after('transaction_date');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn(['opening_balance', 'last_processed_row']);
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('sheet_row_number');
        });
    }
};
