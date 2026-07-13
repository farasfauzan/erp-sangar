<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('opnames', function (Blueprint $table) {
            $table->integer('progress_pct')->default(0)->after('progress_percentage');
            $table->json('progress_items')->nullable()->after('progress_pct');
        });
    }

    public function down(): void
    {
        Schema::table('opnames', function (Blueprint $table) {
            $table->dropColumn(['progress_pct', 'progress_items']);
        });
    }
};
