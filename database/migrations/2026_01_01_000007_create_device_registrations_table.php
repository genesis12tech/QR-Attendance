<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_registrations', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('device_fingerprint');
            $table->string('device_type')->nullable();
            $table->string('platform')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamp('registered_at');
            $table->timestamps();

            $table->unique(['user_id', 'device_fingerprint']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_registrations');
    }
};
