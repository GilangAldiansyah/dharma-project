<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('part_materials', function (Blueprint $table) {
            $table->id();
            $table->string('part_id')->unique();
            $table->foreignId('material_id')->constrained('materials')->onDelete('cascade');
            $table->string('nama_part');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('part_materials');
    }
};
