<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('efakturs', function (Blueprint $table) {
            $table->id();
            $table->string('faktur_number')->unique();
            $table->date('faktur_date');
            $table->string('npwp_penjual', 30);
            $table->string('nama_penjual');
            $table->string('npwp_pembeli', 30)->nullable();
            $table->string('nama_pembeli')->nullable();
            $table->decimal('dpp', 20, 2)->default(0);
            $table->decimal('ppn', 20, 2)->default(0);
            $table->decimal('ppnbm', 20, 2)->default(0);
            $table->enum('status', ['draft', 'validated', 'submitted', 'approved', 'rejected'])->default('draft');
            $table->text('validation_errors')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('project_id')->nullable();
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('faktur_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('efakturs');
    }
};
