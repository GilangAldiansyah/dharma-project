<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dies', function (Blueprint $table) {
            $table->string('id_sap')->primary();
            $table->string('no_part');
            $table->string('nama_dies');
            $table->string('line');
            $table->string('kategori')->nullable();
            $table->enum('status', ['active', 'slow_moving', 'discontinue', 'ohp', 'service_part', 'dies_common', 'plan_relokasi', 'alih_loading'])->default('active');
            $table->boolean('is_common')->default(false);
            $table->integer('std_stroke')->default(0);
            $table->string('freq_maintenance')->nullable();
            $table->integer('freq_maintenance_day')->nullable();
            $table->integer('cav')->default(1);
            $table->integer('forecast_per_day')->default(0);
            $table->integer('current_stroke')->default(0);
            $table->integer('total_stroke')->default(0);
            $table->date('last_mtc_date')->nullable();
            $table->timestamps();
        });

        Schema::create('dies_processes', function (Blueprint $table) {
            $table->id();
            $table->string('dies_id');
            $table->foreign('dies_id')->references('id_sap')->on('dies')->onDelete('cascade');
            $table->string('process_name');
            $table->string('tonase')->nullable();
            $table->timestamps();
        });

        Schema::create('dies_preventive', function (Blueprint $table) {
            $table->id();
            $table->string('report_no')->unique();
            $table->string('dies_id');
            $table->foreign('dies_id')->references('id_sap')->on('dies')->onDelete('cascade');
            $table->string('pic_name');
            $table->date('report_date');
            $table->integer('stroke_at_maintenance')->default(0);
            $table->text('repair_process')->nullable();
            $table->text('repair_action')->nullable();
            $table->json('photos')->nullable();
            $table->enum('status', ['pending', 'in_progress', 'completed'])->default('pending');
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

       Schema::create('dies_corrective', function (Blueprint $table) {
            $table->id();
            $table->string('report_no')->unique();
            $table->string('dies_id');
            $table->foreign('dies_id')->references('id_sap')->on('dies')->onDelete('cascade');
            $table->string('pic_name');
            $table->date('report_date');
            $table->integer('stroke_at_maintenance')->default(0);
            $table->text('problem_description');
            $table->text('cause')->nullable();
            $table->text('repair_action');
            $table->text('action')->nullable();
            $table->json('photos')->nullable();
            $table->enum('status', ['open', 'in_progress', 'closed'])->default('open');
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('dies_spareparts', function (Blueprint $table) {
            $table->id();
            $table->string('sparepart_code')->unique();
            $table->string('sparepart_name');
            $table->string('unit')->default('pcs');
            $table->integer('stok')->default(0);
            $table->integer('stok_minimum')->default(0);
            $table->timestamps();
        });

        Schema::create('dies_history_sparepart', function (Blueprint $table) {
            $table->id();
            $table->enum('tipe', ['preventive', 'corrective', 'reguler']);
            $table->unsignedBigInteger('maintenance_id')->nullable();
            $table->foreignId('sparepart_id')->constrained('dies_spareparts')->onDelete('restrict');
            $table->string('dies_id')->nullable();
            $table->foreign('dies_id')->references('id_sap')->on('dies')->nullOnDelete();
            $table->integer('quantity');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['tipe', 'maintenance_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dies_history_sparepart');
        Schema::dropIfExists('dies_spareparts');
        Schema::dropIfExists('dies_corrective');
        Schema::dropIfExists('dies_preventive');
        Schema::dropIfExists('dies_processes');
        Schema::dropIfExists('dies');
    }
};
