<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pengembalian_materials', function (Blueprint $table) {
            $table->id();
            $table->string('pengembalian_id')->unique(); // PGM-YYMMDD-0001
            $table->foreignId('transaksi_material_id')->constrained('transaksi_materials')->onDelete('cascade');
            $table->date('tanggal_pengembalian');
            $table->decimal('qty_pengembalian', 10, 2);
            $table->json('foto')->nullable();
            $table->text('keterangan')->nullable();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->timestamps();
            $table->softDeletes();

            $table->index('pengembalian_id');
            $table->index('tanggal_pengembalian');
            $table->index('transaksi_material_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengembalian_materials');
    }
};
