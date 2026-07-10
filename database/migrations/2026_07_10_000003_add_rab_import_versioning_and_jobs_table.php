<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rab_import_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('file_path');
            $table->string('file_name');
            $table->string('file_type'); // xlsx, xls, csv
            $table->string('status')->default('PENDING'); // PENDING, PROCESSING, VALIDATED, IMPORTING, COMPLETED, FAILED
            $table->integer('total_rows')->default(0);
            $table->integer('processed_rows')->default(0);
            $table->json('errors')->nullable();
            $table->json('diff')->nullable();
            $table->timestamps();
        });

        Schema::table('rab_budgets', function (Blueprint $table) {
            $table->integer('version')->default(1)->after('project_id');
            $table->foreignId('rab_import_job_id')->nullable()->after('version')->constrained('rab_import_jobs')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('rab_budgets', function (Blueprint $table) {
            $table->dropForeign(['rab_import_job_id']);
            $table->dropColumn(['version', 'rab_import_job_id']);
        });

        Schema::dropIfExists('rab_import_jobs');
    }
};
