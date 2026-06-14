<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        // Add department field to students table
        Schema::table('students', function (Blueprint $table) {
            $table->string('department')->nullable()->after('class_id');
        });

        // Add department field to subjects table
        Schema::table('subjects', function (Blueprint $table) {
            $table->string('department')->nullable()->after('assignment_scope');
        });
    }

    public function down()
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn('department');
        });

        Schema::table('subjects', function (Blueprint $table) {
            $table->dropColumn('department');
        });
    }
};
