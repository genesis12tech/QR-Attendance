<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->constrained()->restrictOnDelete();
            $table->string('roll_no')->unique();
            $table->string('batch_year', 4);
            $table->string('section')->nullable();
            $table->enum('status', ['active', 'suspended', 'graduated', 'dropped'])->default('active');
            $table->softDeletes();
            $table->timestamps();
            $table->index('department_id');
            $table->index('status');
            $table->index('batch_year');
            $table->index(['department_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
