<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fund_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fund_request_id')->constrained('fund_requests');
            $table->string('receipt_number')->unique();
            $table->decimal('amount', 15, 2);
            $table->string('status')->default('RECEIVED'); // RECEIVED, CONFIRMED, DISPUTED
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('received_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fund_receipts');
    }
};
