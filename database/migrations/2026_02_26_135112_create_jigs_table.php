<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jigs', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->string('name');
            $table->enum('kategori', ['regular', 'slow_moving', 'discontinue']);
            $table->string('line');
            $table->foreignId('pic_id')->constrained('users')->onDelete('restrict');
            $table->timestamps();
        });

        Schema::create('spareparts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('satuan');
            $table->decimal('stok', 10, 2)->default(0);
            $table->decimal('stok_minimum', 10, 2)->default(0)->comment('Alert if stok below this');
            $table->timestamps();
        });

        Schema::create('pm_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jig_id')->constrained('jigs')->onDelete('cascade');
            $table->enum('interval', ['1_bulan', '3_bulan']);
            $table->year('tahun');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('pm_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pm_schedule_id')->constrained('pm_schedules')->onDelete('cascade');
            $table->foreignId('pic_id')->constrained('users')->onDelete('restrict');
            $table->date('planned_week_start');
            $table->date('planned_week_end');
            $table->date('actual_date')->nullable();
            $table->string('photo')->nullable();
            $table->enum('condition', ['ok', 'nok'])->nullable();
            $table->text('notes')->nullable();
            $table->enum('status', ['pending', 'done', 'late'])->default('pending');
            $table->timestamps();
        });

        Schema::create('cm_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jig_id')->constrained('jigs')->onDelete('cascade');
            $table->foreignId('pic_id')->constrained('users')->onDelete('restrict');
            $table->date('report_date');
            $table->text('description');
            $table->string('photo')->nullable();
            $table->enum('status', ['open', 'in_progress', 'closed'])->default('open');
            $table->text('action')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->onDelete('restrict');
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('report_spareparts', function (Blueprint $table) {
            $table->id();
            $table->enum('report_type', ['pm', 'cm']);
            $table->unsignedBigInteger('report_id');
            $table->foreignId('sparepart_id')->constrained('spareparts')->onDelete('restrict');
            $table->decimal('qty', 8, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_spareparts');
        Schema::dropIfExists('cm_reports');
        Schema::dropIfExists('pm_reports');
        Schema::dropIfExists('pm_schedules');
        Schema::dropIfExists('spareparts');
        Schema::dropIfExists('jigs');
    }
};
