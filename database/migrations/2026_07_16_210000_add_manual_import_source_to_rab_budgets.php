<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rab_budgets', function (Blueprint $table) {
            $table->string('source_import_key', 64)->nullable()->after('rab_import_job_id');
            $table->string('source_file_fingerprint', 64)->nullable()->after('source_import_key');
            $table->string('source_sheet')->nullable()->after('source_file_fingerprint');
            $table->unsignedInteger('source_row')->nullable()->after('source_sheet');
            $table->foreignId('imported_by')->nullable()->after('source_row')->constrained('users')->nullOnDelete();

            $table->unique(['project_id', 'source_import_key'], 'rab_budgets_project_source_unique');
            $table->index(['project_id', 'source_file_fingerprint', 'source_sheet'], 'rab_budgets_source_status_index');
        });
    }

    public function down(): void
    {
        Schema::table('rab_budgets', function (Blueprint $table) {
            $table->dropUnique('rab_budgets_project_source_unique');
            $table->dropIndex('rab_budgets_source_status_index');
            $table->dropForeign(['imported_by']);
            $table->dropColumn([
                'source_import_key',
                'source_file_fingerprint',
                'source_sheet',
                'source_row',
                'imported_by',
            ]);
        });
    }
};
