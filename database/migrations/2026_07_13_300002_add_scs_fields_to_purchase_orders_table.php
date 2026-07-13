<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->string('po_type')->default('PURCHASE_ORDER')->after('supplier_name');
            $table->unsignedInteger('addendum_number')->nullable()->after('po_type');
            $table->text('supplier_address')->nullable()->after('addendum_number');
            $table->string('supplier_phone')->nullable()->after('supplier_address');
            $table->string('supplier_contact_person')->nullable()->after('supplier_phone');
            $table->string('project_location')->nullable()->after('supplier_contact_person');
            $table->decimal('discount', 15, 2)->default(0)->after('project_location');
            $table->boolean('include_ppn')->default(true)->after('discount');
            $table->text('catatan')->nullable()->after('include_ppn');
            $table->string('faktur_pajak_nama')->nullable()->after('catatan');
            $table->string('faktur_pajak_npwp')->nullable()->after('faktur_pajak_nama');
            $table->text('faktur_pajak_alamat')->nullable()->after('faktur_pajak_npwp');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropColumn([
                'po_type',
                'addendum_number',
                'supplier_address',
                'supplier_phone',
                'supplier_contact_person',
                'project_location',
                'discount',
                'include_ppn',
                'catatan',
                'faktur_pajak_nama',
                'faktur_pajak_npwp',
                'faktur_pajak_alamat',
            ]);
        });
    }
};
