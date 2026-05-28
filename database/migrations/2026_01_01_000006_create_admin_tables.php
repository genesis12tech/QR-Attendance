<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courses', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->id();
            $table->foreignId('department_id')->constrained()->restrictOnDelete();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('semester');
            $table->unsignedTinyInteger('credits');
            $table->unsignedTinyInteger('min_attendance_pct')->default(75);
            $table->softDeletes();
            $table->timestamps();

            $table->index('department_id');
        });

        Schema::create('class_groups', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();

            $table->index('course_id');
        });

        Schema::create('rooms', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->id();
            $table->string('name');
            $table->string('building')->nullable();
            $table->unsignedSmallInteger('capacity')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->unsignedSmallInteger('geofence_radius_m')->nullable();
            $table->string('beacon_id')->nullable();
            $table->string('wifi_ssid')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('is_active');
        });

        Schema::create('timetables', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('class_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('faculty_id')->constrained('faculty')->restrictOnDelete();
            $table->foreignId('room_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('day_of_week', ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday']);
            $table->time('start_time');
            $table->time('end_time');
            $table->date('effective_from');
            $table->date('effective_until')->nullable();
            $table->timestamps();

            $table->index('faculty_id');
            $table->index('course_id');
            $table->index(['day_of_week', 'start_time']);
        });

        Schema::create('enrollments', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->restrictOnDelete();
            $table->foreignId('class_group_id')->constrained()->restrictOnDelete();
            $table->date('enrolled_at');
            $table->enum('status', ['active', 'dropped', 'completed'])->default('active');
            $table->timestamps();

            $table->unique(['student_id', 'course_id', 'class_group_id'], 'uq_enrollments_student_course_group');
            $table->index('course_id');
            $table->index('class_group_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enrollments');
        Schema::dropIfExists('timetables');
        Schema::dropIfExists('rooms');
        Schema::dropIfExists('class_groups');
        Schema::dropIfExists('courses');
    }
};
