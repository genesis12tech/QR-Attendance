<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('security_policies', function (Blueprint $table) {
            $table->string('policy_name')->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('security_policies', function (Blueprint $table) {
            $table->dropColumn('policy_name');
        });
    }
};
