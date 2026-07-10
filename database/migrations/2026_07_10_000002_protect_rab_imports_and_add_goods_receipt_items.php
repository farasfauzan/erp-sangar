<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rab_budgets', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::create('goods_receipt_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goods_receipt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('po_item_id')->constrained()->cascadeOnDelete();
            $table->decimal('quantity_received', 15, 2);
            $table->timestamps();

            $table->unique(['goods_receipt_id', 'po_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goods_receipt_items');

        Schema::table('rab_budgets', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
