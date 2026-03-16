<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop child table first (has foreign key to die_shop_reports)
        Schema::dropIfExists('die_shop_spareparts');

        // Drop parent table (has foreign key to die_parts)
        Schema::dropIfExists('die_shop_reports');

        // Drop base table
        Schema::dropIfExists('die_parts');
    }

    public function down(): void
    {
        // Recreate die_parts
        Schema::create('die_parts', function (Blueprint $table) {
            $table->id();
            $table->string('part_no')->unique();
            $table->string('part_name');
            $table->text('description')->nullable();
            $table->string('location')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
            $table->softDeletes();
        });

        // Recreate die_shop_reports
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
            $table->json('photos')->nullable();
            $table->enum('status', ['pending', 'in_progress', 'completed'])->default('pending');
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();
        });

        // Recreate die_shop_spareparts
        Schema::create('die_shop_spareparts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('die_shop_report_id')->constrained()->onDelete('cascade');
            $table->string('sparepart_name');
            $table->string('sparepart_code')->nullable();
            $table->integer('quantity');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }
};
