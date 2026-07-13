<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('taxes', function (Blueprint $table) {
            if (!Schema::hasColumn('taxes', 'restitusi_status')) {
                $table->enum('restitusi_status', ['none', 'pending', 'approved', 'rejected', 'paid'])->default('none')->after('description');
                $table->decimal('restitusi_amount', 20, 2)->nullable()->after('restitusi_status');
                $table->text('restitusi_notes')->nullable()->after('restitusi_amount');
                $table->timestamp('restitusi_approved_at')->nullable()->after('restitusi_notes');
                $table->unsignedBigInteger('restitusi_approved_by')->nullable()->after('restitusi_approved_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('taxes', function (Blueprint $table) {
            if (Schema::hasColumn('taxes', 'restitusi_status')) {
                $table->dropColumn(['restitusi_status', 'restitusi_amount', 'restitusi_notes', 'restitusi_approved_at', 'restitusi_approved_by']);
            }
        });
    }
};
