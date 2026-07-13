<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('spks', function (Blueprint $table) {
            $table->string('spk_type', 20)->default('SUBKON')->after('spk_number');
            $table->boolean('include_ppn')->default(true)->after('total_amount');
            $table->date('jadwal_kirim')->nullable()->after('payment_terms');
        });
    }

    public function down(): void
    {
        Schema::table('spks', function (Blueprint $table) {
            $table->dropColumn(['spk_type', 'include_ppn', 'jadwal_kirim']);
        });
    }
};
