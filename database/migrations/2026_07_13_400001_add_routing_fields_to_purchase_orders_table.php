<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->string('po_level')->default('PROJECT')->after('status'); // PROJECT, SUPPLIER
            $table->string('routed_to')->nullable()->after('po_level'); // PURCHASE_ORDER, SPK
            $table->foreignId('routed_by')->nullable()->after('routed_to')->constrained('users');
            $table->timestamp('routed_at')->nullable()->after('routed_by');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropColumn(['po_level', 'routed_to', 'routed_by', 'routed_at']);
        });
    }
};
