<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('improvement_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jig_id')->constrained('jigs')->cascadeOnDelete();
            $table->foreignId('pic_id')->constrained('users')->cascadeOnDelete();
            $table->dateTime('report_date');
            $table->text('description')->nullable();
            $table->text('penyebab')->nullable();
            $table->text('perbaikan')->nullable();
            $table->string('photo')->nullable();
            $table->string('photo_perbaikan')->nullable();
            $table->enum('status', ['open', 'in_progress', 'closed'])->default('open');
            $table->text('action')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('closed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('improvement_reports');
    }
};
