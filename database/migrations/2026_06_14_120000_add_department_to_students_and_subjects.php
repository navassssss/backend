<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        // 1. Create departments table
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        // 2. Add department_id to students table
        Schema::table('students', function (Blueprint $table) {
            $table->foreignId('department_id')->nullable()->after('class_id')->constrained('departments')->nullOnDelete();
        });

        // 3. Add department_id to subjects table
        Schema::table('subjects', function (Blueprint $table) {
            $table->foreignId('department_id')->nullable()->after('assignment_scope')->constrained('departments')->nullOnDelete();
        });
    }

    public function down()
    {
        Schema::table('subjects', function (Blueprint $table) {
            $table->dropForeign(['department_id']);
            $table->dropColumn('department_id');
        });

        Schema::table('students', function (Blueprint $table) {
            $table->dropForeign(['department_id']);
            $table->dropColumn('department_id');
        });

        Schema::dropIfExists('departments');
    }
};
