<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lines', function (Blueprint $table) {
            $table->id();
            $table->string('line_code')->unique(); // Contoh: LINE-A-01
            $table->string('line_name'); // Contoh: Production Line A1
            $table->string('plant'); // Plant A, Plant B, etc
            $table->string('qr_code')->unique(); // QR Code untuk scan
            $table->enum('status', ['operating', 'stopped', 'maintenance'])->default('stopped');
            $table->timestamp('last_operation_start')->nullable();
            $table->timestamp('last_line_stop')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lines');
    }
};
