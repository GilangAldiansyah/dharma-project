<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_reports', function (Blueprint $table) {
            $table->id();

            // Relations
            $table->foreignId('line_id')->constrained('lines')->onDelete('cascade');
            $table->foreignId('machine_id')->constrained('machines')->onDelete('cascade');
            $table->foreignId('line_operation_id')->nullable()->constrained('line_operations')->onDelete('set null');

            // Report Info
            $table->string('report_number')->unique();
            $table->text('problem')->nullable();
            $table->enum('status', ['Dilaporkan', 'Sedang Diperbaiki', 'Selesai'])->default('Dilaporkan');

            // Reporter
            $table->string('reported_by')->nullable();

            // Timestamps
            $table->timestamp('reported_at'); // Waktu laporan dibuat
            $table->timestamp('line_stopped_at')->nullable(); // Waktu line stop (sama dengan reported_at)
            $table->timestamp('started_at')->nullable(); // Waktu mulai perbaikan
            $table->timestamp('completed_at')->nullable(); // Waktu selesai perbaikan

            // Calculated durations
            $table->integer('repair_duration_minutes')->nullable(); // Durasi perbaikan (started_at -> completed_at)
            $table->integer('line_stop_duration_minutes')->nullable(); // Total durasi line stop (line_stopped_at -> completed_at)

            $table->timestamps();

            $table->index(['line_id', 'status']);
            $table->index('reported_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_reports');
    }
};
