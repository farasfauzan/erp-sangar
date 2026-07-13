<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('basts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opname_id')->constrained('opnames')->cascadeOnDelete();
            $table->string('bast_number')->unique();
            $table->date('bast_date');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('basts');
    }
};
