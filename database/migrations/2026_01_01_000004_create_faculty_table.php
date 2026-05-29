<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('faculty', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->constrained()->restrictOnDelete();
            $table->string('employee_code')->unique();
            $table->string('designation')->nullable();
            $table->enum('status', ['active', 'on_leave', 'inactive'])->default('active');
            $table->timestamps();
            $table->index('department_id');
            $table->index('status');
        });

        // Resolve circular FK: departments.head_faculty_id → faculty
        Schema::table('departments', function (Blueprint $table) {
            $table->foreign('head_faculty_id')
                ->references('id')->on('faculty')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            $table->dropForeign(['head_faculty_id']);
        });

        Schema::dropIfExists('faculty');
    }
};
