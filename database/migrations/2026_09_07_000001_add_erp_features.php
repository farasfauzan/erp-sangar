<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add status, parent_id to rab_budgets for workflow + roll-up
        Schema::table('rab_budgets', function (Blueprint $table) {
            $table->string('status')->default('DRAFT')->after('category'); // DRAFT → PENDING → APPROVED → REJECTED
            $table->unsignedBigInteger('parent_id')->nullable()->after('status'); // for hierarchy
            $table->foreign('parent_id')->references('id')->on('rab_budgets')->nullOnDelete();
            $table->unsignedBigInteger('approved_by')->nullable()->after('parent_id');
            $table->timestamp('approved_at')->nullable()->after('approved_by');
        });

        // 2. Inventory stocks linked to RAB items
        Schema::create('inventory_stocks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id');
            $table->unsignedBigInteger('rab_budget_id')->nullable();
            $table->string('item_name');
            $table->string('unit')->nullable();
            $table->decimal('quantity', 15, 2)->default(0);
            $table->decimal('min_quantity', 15, 2)->default(0); // reorder level
            $table->string('location')->nullable();
            $table->timestamps();

            $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
            $table->foreign('rab_budget_id')->references('id')->on('rab_budgets')->nullOnDelete();
        });

        // 3. Purchase requisitions (material requests from field)
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
            $table->string('status')->default('DRAFT'); // DRAFT → PENDING → APPROVED → REJECTED → ORDERED
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
            $table->foreign('rab_budget_id')->references('id')->on('rab_budgets')->nullOnDelete();
            $table->foreign('requested_by')->references('id')->on('users');
            $table->foreign('approved_by')->references('id')->on('users')->nullOnDelete();
        });

        // 4. Audit logs — generic for any model
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('auditable_type'); // e.g. RabBudget, PurchaseOrder
            $table->unsignedBigInteger('auditable_id');
            $table->string('action'); // CREATED, UPDATED, DELETED, STATUS_CHANGED
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
        Schema::dropIfExists('inventory_stocks');
        Schema::table('rab_budgets', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropColumn(['status', 'parent_id', 'approved_by', 'approved_at']);
        });
    }
};