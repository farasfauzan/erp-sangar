<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
        public function up(): void
    {
        Schema::create('opnames', function (Blueprint $table) {
            $table->id();
            $table->foreignId('spk_id')->constrained('spks');
            $table->string('opname_number')->unique();
            $table->date('date');
            $table->decimal('progress_percentage', 5, 2);
            $table->decimal('amount', 15, 2);
            $table->string('status')->default('PENDING'); // PENDING, APPROVED
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('opnames');
    }
};
