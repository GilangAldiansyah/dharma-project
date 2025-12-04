<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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

    public function down(): void
    {
        Schema::dropIfExists('die_shop_spareparts');
    }
};
