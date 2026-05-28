<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('student')->comment('super_admin,admin,faculty,student')->change();
            $table->string('status')->default('active')->comment('active,suspended,inactive')->change();
        });

        Schema::table('students', function (Blueprint $table) {
            $table->string('status')->default('active')->comment('active,suspended,graduated,dropped')->change();
        });

        Schema::table('faculty', function (Blueprint $table) {
            $table->string('status')->default('active')->comment('active,on_leave,inactive')->change();
        });

        Schema::table('admin_role_assignments', function (Blueprint $table) {
            $table->string('role')->comment('admin,faculty')->change();
        });

        Schema::table('enrollments', function (Blueprint $table) {
            $table->string('status')->default('active')->comment('active,dropped,completed')->change();
        });

        Schema::table('attendance_sessions', function (Blueprint $table) {
            $table->string('status')->default('pending')->comment('pending,active,paused,closed')->change();
        });

        Schema::table('attendance_records', function (Blueprint $table) {
            $table->string('status')->default('pending_review')->comment('present,late,absent,pending_review,rejected')->change();
        });

        Schema::table('session_exports', function (Blueprint $table) {
            $table->string('format')->comment('pdf,csv,xlsx')->change();
            $table->string('status')->default('pending')->comment('pending,processing,ready,failed')->change();
        });

        Schema::table('proxy_flags', function (Blueprint $table) {
            $table->string('severity')->comment('low,medium,high,critical')->change();
            $table->string('review_status')->default('pending')->comment('pending,approved,rejected')->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['super_admin', 'admin', 'faculty', 'student'])->default('student')->change();
            $table->enum('status', ['active', 'suspended', 'inactive'])->default('active')->change();
        });

        Schema::table('students', function (Blueprint $table) {
            $table->enum('status', ['active', 'graduated', 'suspended', 'withdrawn'])->default('active')->change();
        });

        Schema::table('faculty', function (Blueprint $table) {
            $table->enum('status', ['active', 'inactive', 'on_leave'])->default('active')->change();
        });

        Schema::table('admin_role_assignments', function (Blueprint $table) {
            $table->enum('role', ['admin', 'faculty'])->change();
        });

        Schema::table('enrollments', function (Blueprint $table) {
            $table->enum('status', ['active', 'dropped', 'completed'])->default('active')->change();
        });

        Schema::table('attendance_sessions', function (Blueprint $table) {
            $table->enum('status', ['pending', 'active', 'paused', 'closed'])->default('pending')->change();
        });

        Schema::table('attendance_records', function (Blueprint $table) {
            $table->enum('status', ['present', 'late', 'absent', 'pending_review', 'rejected'])->default('pending_review')->change();
        });

        Schema::table('session_exports', function (Blueprint $table) {
            $table->enum('format', ['pdf', 'csv', 'xlsx'])->change();
            $table->enum('status', ['pending', 'processing', 'ready', 'failed'])->default('pending')->change();
        });

        Schema::table('proxy_flags', function (Blueprint $table) {
            $table->enum('severity', ['low', 'medium', 'high', 'critical'])->change();
            $table->enum('review_status', ['pending', 'approved', 'rejected'])->default('pending')->change();
        });
    }
};
