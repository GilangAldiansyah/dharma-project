<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transaksi_materials', function (Blueprint $table) {
            $table->id();
            $table->string('transaksi_id')->unique();
            $table->date('tanggal');
            $table->integer('shift'); // 1, 2, 3
            $table->foreignId('material_id')->constrained('materials')->onDelete('cascade');
            $table->foreignId('part_material_id')->nullable()->constrained('part_materials')->onDelete('set null');
            $table->decimal('qty', 10, 2);
            $table->json('foto')->nullable(); // Array of image paths
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaksi_materials');
    }
};
