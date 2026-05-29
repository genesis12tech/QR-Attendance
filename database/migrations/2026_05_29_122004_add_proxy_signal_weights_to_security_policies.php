<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('security_policies', function (Blueprint $table) {
            $table->unsignedTinyInteger('w_gps')->default(20)->after('clock_skew_seconds');
            $table->unsignedTinyInteger('w_device')->default(20)->after('w_gps');
            $table->unsignedTinyInteger('w_clock_skew')->default(20)->after('w_device');
            $table->unsignedTinyInteger('w_wifi')->default(20)->after('w_clock_skew');
            $table->unsignedTinyInteger('w_beacon')->default(20)->after('w_wifi');
            $table->unsignedTinyInteger('w_ip_cluster')->default(20)->after('w_beacon');
            $table->unsignedTinyInteger('w_speed')->default(20)->after('w_ip_cluster');
            $table->unsignedTinyInteger('w_peer_scan')->default(20)->after('w_speed');
            $table->unsignedTinyInteger('w_biometric')->default(20)->after('w_peer_scan');
        });
    }

    public function down(): void
    {
        Schema::table('security_policies', function (Blueprint $table) {
            $table->dropColumn([
                'w_gps', 'w_device', 'w_clock_skew', 'w_wifi', 'w_beacon',
                'w_ip_cluster', 'w_speed', 'w_peer_scan', 'w_biometric',
            ]);
        });
    }
};
