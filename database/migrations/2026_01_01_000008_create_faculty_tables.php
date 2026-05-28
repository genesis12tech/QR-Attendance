<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_sessions', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('faculty_id')->constrained('faculty')->restrictOnDelete();
            $table->foreignId('course_id')->constrained()->restrictOnDelete();
            $table->foreignId('class_group_id')->constrained()->restrictOnDelete();
            $table->foreignId('room_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('timetable_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('status', ['pending', 'active', 'paused', 'closed'])->default('pending');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->text('close_reason')->nullable();
            $table->unsignedSmallInteger('total_enrolled')->default(0);
            $table->unsignedSmallInteger('total_present')->default(0);
            $table->unsignedSmallInteger('total_late')->default(0);
            $table->unsignedSmallInteger('total_absent')->default(0);
            $table->timestamps();

            $table->index('faculty_id');
            $table->index('course_id');
            $table->index('status');
            $table->index('started_at');
            $table->index(['faculty_id', 'status']);
        });

        Schema::create('qr_challenges', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->id();
            $table->foreignId('session_id')->constrained('attendance_sessions')->cascadeOnDelete();
            $table->char('jti_hash', 64);
            $table->unsignedBigInteger('slot_no');
            $table->timestamp('not_before');
            $table->timestamp('expires_at');
            $table->foreignId('room_id')->constrained()->restrictOnDelete();
            $table->boolean('was_used')->default(false);
            $table->timestamp('created_at')->useCurrent();

            $table->unique('jti_hash', 'uq_qr_jti_hash');
            $table->index(['session_id', 'slot_no'], 'idx_qr_session_slot');
            $table->index('expires_at', 'idx_qr_expires');
        });

        Schema::create('attendance_records', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->id();
            $table->foreignId('attendance_session_id')->constrained('attendance_sessions')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->foreignId('enrollment_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('status', ['present', 'late', 'absent', 'pending_review', 'rejected'])->default('pending_review');
            $table->timestamp('marked_at')->nullable();
            $table->unsignedTinyInteger('risk_score')->default(0);
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->foreignId('device_id')->nullable()->constrained('device_registrations')->nullOnDelete();
            $table->json('evidence_json')->nullable();
            $table->foreignId('override_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('override_reason')->nullable();
            $table->timestamp('overridden_at')->nullable();
            $table->timestamps();

            $table->unique(['attendance_session_id', 'student_id'], 'uq_attendance_session_student');
            $table->index('attendance_session_id');
            $table->index('student_id');
            $table->index('status');
            $table->index('risk_score');
        });

        Schema::create('session_exports', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->id();
            $table->foreignId('session_id')->constrained('attendance_sessions')->cascadeOnDelete();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->enum('format', ['pdf', 'csv', 'xlsx']);
            $table->enum('status', ['pending', 'processing', 'ready', 'failed'])->default('pending');
            $table->string('file_path')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index('session_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_exports');
        Schema::dropIfExists('attendance_records');
        Schema::dropIfExists('qr_challenges');
        Schema::dropIfExists('attendance_sessions');
    }
};
