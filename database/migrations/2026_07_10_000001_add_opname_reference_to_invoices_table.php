<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('opname_id')
                ->nullable()
                ->after('invoiceable_id')
                ->constrained('opnames')
                ->nullOnDelete()
                ->unique();
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['opname_id']);
            $table->dropUnique(['opname_id']);
            $table->dropColumn('opname_id');
        });
    }
};
