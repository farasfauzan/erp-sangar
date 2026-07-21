<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropColumn('include_ppn');
            $table->decimal('tax_rate', 5, 2)->default(0)->after('discount');
        });

        Schema::table('spks', function (Blueprint $table) {
            $table->dropColumn('include_ppn');
            $table->decimal('tax_rate', 5, 2)->default(0)->after('subtotal');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropColumn('tax_rate');
            $table->boolean('include_ppn')->default(true);
        });

        Schema::table('spks', function (Blueprint $table) {
            $table->dropColumn('tax_rate');
            $table->boolean('include_ppn')->default(true);
        });
    }
};