<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tabel untuk menyimpan forecast bulanan (Product level)
        Schema::create('monthly_forecasts', function (Blueprint $table) {
            $table->id();
            $table->string('sap_no')->index(); // SAP NO dari Output Product
            $table->string('product_unit')->nullable();
            $table->string('part_name')->nullable();
            $table->string('type')->nullable(); // D74, D55, D30, dll
            $table->integer('year'); // 2025
            $table->integer('month'); // 1-12
            $table->integer('forecast_qty')->default(0); // Total forecast bulan ini (misal: 880)
            $table->integer('working_days')->default(20); // Hari kerja (misal: 20)
            $table->decimal('qty_per_day', 10, 2)->default(0); // Auto-calculate: 880/20 = 44
            $table->timestamps();

            $table->unique(['sap_no', 'year', 'month'], 'forecast_unique');
            $table->index(['year', 'month']);
        });

        // Tambah kolom di output_products untuk tracking forecast
        Schema::table('output_products', function (Blueprint $table) {
            $table->integer('forecast_qty')->default(0)->after('qty_day');
            $table->integer('working_days')->default(20)->after('forecast_qty');
            $table->text('sync_note')->nullable()->after('working_days'); // Catatan sync
        });

        // Tambah kolom di daily_stocks untuk tracking forecast dari BOM
        Schema::table('daily_stocks', function (Blueprint $table) {
            $table->decimal('qty_day_from_forecast', 10, 2)->default(0)->after('qty_day');
            $table->text('bom_calculation')->nullable()->after('qty_day_from_forecast'); // Log perhitungan BOM
        });
    }

    public function down(): void
    {
        Schema::table('daily_stocks', function (Blueprint $table) {
            $table->dropColumn(['qty_day_from_forecast', 'bom_calculation']);
        });

        Schema::table('output_products', function (Blueprint $table) {
            $table->dropColumn(['forecast_qty', 'working_days', 'sync_note']);
        });

        Schema::dropIfExists('monthly_forecasts');
    }
};
