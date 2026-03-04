<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. pm_schedules — tambah target_week
        Schema::table('pm_schedules', function (Blueprint $table) {
            $table->unsignedTinyInteger('target_week')
                  ->default(3)
                  ->after('tahun')
                  ->comment('Minggu ke-berapa PM dilakukan (1-5)');
        });

        // 2. cm_reports — tambah penyebab, perbaikan, photo_perbaikan
        Schema::table('cm_reports', function (Blueprint $table) {
            $table->text('penyebab')->nullable()->after('description')->comment('Root cause kerusakan');
            $table->text('perbaikan')->nullable()->after('penyebab')->comment('Tindakan perbaikan yang dilakukan');
            $table->string('photo_perbaikan')->nullable()->after('photo')->comment('Foto setelah perbaikan');
        });

        // 3. report_spareparts — tambah notes
        Schema::table('report_spareparts', function (Blueprint $table) {
            $table->text('notes')->nullable()->after('qty')->comment('Keterangan penggunaan sparepart');
        });

        // 4. sparepart_histories — tabel baru untuk log transaksi stok
        Schema::create('sparepart_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sparepart_id')->constrained('spareparts')->onDelete('cascade');
            $table->enum('tipe', ['masuk', 'keluar'])->comment('masuk=tambah stok, keluar=pakai di PM/CM');
            $table->enum('report_type', ['pm', 'cm', 'manual'])->nullable();
            $table->unsignedBigInteger('report_id')->nullable()->comment('ID pm_report atau cm_report');
            $table->decimal('qty', 8, 2);
            $table->decimal('stok_before', 10, 2)->comment('Stok sebelum transaksi');
            $table->decimal('stok_after', 10, 2)->comment('Stok setelah transaksi');
            $table->text('notes')->nullable();
            $table->foreignId('user_id')->constrained('users')->onDelete('restrict');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sparepart_histories');

        Schema::table('report_spareparts', function (Blueprint $table) {
            $table->dropColumn('notes');
        });

        Schema::table('cm_reports', function (Blueprint $table) {
            $table->dropColumn(['penyebab', 'perbaikan', 'photo_perbaikan']);
        });

        Schema::table('pm_schedules', function (Blueprint $table) {
            $table->dropColumn('target_week');
        });
    }
};
