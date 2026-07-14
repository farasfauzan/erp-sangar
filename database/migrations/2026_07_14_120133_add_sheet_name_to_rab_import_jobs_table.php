<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rab_import_jobs', function (Blueprint $table) {
            $table->string('sheet_name')->nullable()->after('file_type');
        });
    }

    public function down(): void
    {
        Schema::table('rab_import_jobs', function (Blueprint $table) {
            $table->dropColumn('sheet_name');
        });
    }
};
