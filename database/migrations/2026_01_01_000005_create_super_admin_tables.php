<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('security_policies', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->id();
            $table->unsignedSmallInteger('qr_expiry_seconds')->default(30);
            $table->unsignedTinyInteger('risk_auto_reject')->default(80);
            $table->unsignedTinyInteger('risk_pending_review')->default(50);
            $table->unsignedSmallInteger('late_threshold_mins')->default(15);
            $table->unsignedSmallInteger('geofence_radius_m')->default(50);
            $table->boolean('device_binding_required')->default(true);
            $table->unsignedSmallInteger('clock_skew_seconds')->default(60);
            $table->boolean('is_active')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('is_active');
        });

        Schema::create('system_settings', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Schema::create('data_retention_policies', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->id();
            $table->string('entity_type');
            $table->unsignedSmallInteger('retention_days');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_run_at')->nullable();
            $table->timestamps();

            $table->index('entity_type');
            $table->index('is_active');
        });

        Schema::create('admin_role_assignments', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('role', ['admin', 'faculty']);
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('assigned_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('assigned_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('role');
            $table->index('department_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_role_assignments');
        Schema::dropIfExists('data_retention_policies');
        Schema::dropIfExists('system_settings');
        Schema::dropIfExists('security_policies');
    }
};
