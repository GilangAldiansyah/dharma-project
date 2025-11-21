<?php

// ============================================
// 1. MIGRATION FILE - WITH bl_type
// ============================================

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Tabel 1: Daily Stocks (Tabel atas) - DENGAN BL_TYPE
        Schema::create('daily_stocks', function (Blueprint $table) {
            $table->id();

            // TAMBAHAN: Identifier BL1/BL2
            $table->enum('bl_type', ['BL1', 'BL2'])->default('BL1')->comment('Identifier untuk BL1 atau BL2');

            // Material Info
            $table->string('sap_finish')->nullable();
            $table->string('id_sap');
            $table->string('material_name');
            $table->string('part_no')->nullable();
            $table->string('part_name')->nullable();

            // Tambahan kolom sesuai Excel
            $table->string('type')->nullable(); // D30, D55, D26, dll
            $table->integer('qty_unit')->nullable(); // Qty/unit
            $table->integer('qty_day')->nullable(); // Qty/Day

            // Stock Date
            $table->date('stock_date');

            // Stock Awal
            $table->integer('stock_awal')->default(0);

            // Produksi BL 1 (3 shift)
            $table->integer('produksi_shift1')->default(0);
            $table->integer('produksi_shift2')->default(0);
            $table->integer('produksi_shift3')->default(0);

            // OUT (3 shift)
            $table->integer('out_shift1')->default(0);
            $table->integer('out_shift2')->default(0);
            $table->integer('out_shift3')->default(0);

            // NG Single (2 shift)
            $table->integer('ng_shift1')->default(0);
            $table->integer('ng_shift2')->default(0);

            $table->timestamps();

            // Indexes - PENTING untuk performance
            $table->index('stock_date');
            $table->index(['id_sap', 'stock_date']);
            $table->index(['bl_type', 'stock_date']); // TAMBAH INDEX INI
        });

        Schema::create('output_products', function (Blueprint $table) {
            $table->id();

            // TIDAK ADA bl_type - Output products tidak dibagi BL1/BL2

            $table->string('type')->nullable(); // D30, D55, D26, dll
            $table->string('penanggung_jawab')->nullable(); // Nama PIC per type
            $table->string('sap_no');
            $table->string('product_unit');
            $table->integer('qty_day')->nullable();

            // Stock Date
            $table->date('stock_date');

            // OUT (2 shift untuk output)
            $table->integer('out_shift1')->default(0);
            $table->integer('out_shift2')->default(0);

            // NG Unit (2 shift)
            $table->integer('ng_shift1')->default(0);
            $table->integer('ng_shift2')->default(0);

            // Total
            $table->integer('total')->default(0);

            $table->timestamps();

            // Indexes
            $table->index('stock_date');
            $table->index(['type', 'stock_date']);
        });

        // PENTING: Set semua data existing jadi BL1 (jika table sudah ada data)
        // Ini akan dijalankan otomatis saat migrate
    }

    public function down(): void
    {
        Schema::dropIfExists('output_products');
        Schema::dropIfExists('daily_stocks');
    }
};
