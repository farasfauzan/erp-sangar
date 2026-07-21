<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Tambah status dan parent_id pada rab_budgets untuk workflow
        Schema::table('rab_budgets', function (Blueprint $table) {
            $table->string('status')->default('DRAFT')->after('category');
            $table->unsignedBigInteger('parent_id')->nullable()->after('status');
            $table->foreign('parent_id')->references('id')->on('rab_budgets')->nullOnDelete();
            $table->unsignedBigInteger('approved_by')->nullable()->after('parent_id');
            $table->timestamp('approved_at')->nullable()->after('approved_by');
        });

        // 2. Purchase requisitions (permintaan material dari lapangan)
        Schema::create('purchase_requisitions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id');
            $table->unsignedBigInteger('rab_budget_id')->nullable();
            $table->unsignedBigInteger('requested_by');
            $table->string('pr_number')->unique();
            $table->string('item_name');
            $table->string('unit')->nullable();
            $table->decimal('qty_requested', 15, 2);
            $table->decimal('qty_approved', 15, 2)->nullable();
            $table->string('status')->default('DRAFT');
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
            $table->foreign('rab_budget_id')->references('id')->on('rab_budgets')->nullOnDelete();
            $table->foreign('requested_by')->references('id')->on('users');
            $table->foreign('approved_by')->references('id')->on('users')->nullOnDelete();
        });

        // 3. Audit logs — pencatatan riwayat model secara umum
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('auditable_type'); 
            $table->unsignedBigInteger('auditable_id');
            $table->string('action'); 
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users');
            $table->index(['auditable_type', 'auditable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('purchase_requisitions');
        
        Schema::table('rab_budgets', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropColumn(['status', 'parent_id', 'approved_by', 'approved_at']);
        });
    }
};