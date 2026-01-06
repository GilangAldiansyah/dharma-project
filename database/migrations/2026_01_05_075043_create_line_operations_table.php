<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('line_operations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('line_id')->constrained('lines')->onDelete('cascade');
            $table->string('operation_number')->unique(); // OP-2024-0001

            // Start Operation (Scan 1)
            $table->timestamp('started_at');
            $table->string('started_by')->nullable();

            // Line Stop (Scan 2)
            $table->timestamp('stopped_at')->nullable();
            $table->string('stopped_by')->nullable();

            // Calculated
            $table->integer('duration_minutes')->nullable(); // Durasi operasi dalam menit
            $table->decimal('mtbf_hours', 10, 2)->nullable(); // MTBF untuk operasi ini

            $table->enum('status', ['running', 'stopped'])->default('running');
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['line_id', 'started_at']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('line_operations');
    }
};
