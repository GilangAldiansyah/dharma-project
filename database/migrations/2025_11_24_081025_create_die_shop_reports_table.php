<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('die_shop_reports', function (Blueprint $table) {
            $table->id();
            $table->string('report_no')->unique();
            $table->enum('activity_type', ['corrective', 'preventive']);
            $table->string('pic_name');
            $table->date('report_date');
            $table->foreignId('die_part_id')->constrained()->onDelete('cascade');
            $table->text('repair_process');
            $table->text('problem_description');
            $table->text('cause');
            $table->text('repair_action');
            $table->json('photos')->nullable(); // Array of photo paths
            $table->enum('status', ['pending', 'in_progress', 'completed'])->default('pending');
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('die_shop_reports');
    }
};
