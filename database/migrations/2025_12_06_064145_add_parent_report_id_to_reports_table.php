<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->foreignId('parent_report_id')
                ->nullable()
                ->constrained('reports')
                ->nullOnDelete()
                ->after('id');
        });
    }

    public function down()
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->dropForeign(['parent_report_id']);
            $table->dropColumn('parent_report_id');
        });
    }
};
