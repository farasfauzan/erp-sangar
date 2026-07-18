<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rab_import_drafts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->string('file_id')->nullable();
            $table->string('file_fingerprint', 64);
            $table->string('original_name')->nullable();
            $table->string('sheet');
            $table->unsignedInteger('row_number');
            $table->string('code_item')->nullable();
            $table->text('description');
            $table->string('unit')->nullable();
            $table->decimal('volume', 15, 2)->default(0);
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->decimal('total_price', 15, 2)->default(0);
            $table->string('category')->nullable();
            $table->string('item_group')->nullable();
            $table->string('status')->default('DRAFT');
            $table->foreignId('saved_budget_id')->nullable()->constrained('rab_budgets')->nullOnDelete();
            $table->timestamps();

            $table->unique(['project_id', 'file_fingerprint', 'sheet', 'row_number'], 'rab_import_drafts_source_unique');
            $table->index(['project_id', 'file_fingerprint', 'status'], 'rab_import_drafts_lookup_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rab_import_drafts');
    }
};
