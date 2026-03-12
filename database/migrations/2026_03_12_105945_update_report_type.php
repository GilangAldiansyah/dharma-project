<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('report_spareparts', function (Blueprint $table) {
            $table->enum('report_type', ['pm', 'cm', 'improvement'])->change();
        });

        Schema::table('sparepart_histories', function (Blueprint $table) {
            $table->enum('report_type', ['pm', 'cm', 'improvement', 'manual'])->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('report_spareparts', function (Blueprint $table) {
            $table->enum('report_type', ['pm', 'cm'])->change();
        });

        Schema::table('sparepart_histories', function (Blueprint $table) {
            $table->enum('report_type', ['pm', 'cm', 'manual'])->nullable()->change();
        });
    }
};
