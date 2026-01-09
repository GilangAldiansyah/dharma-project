<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('pengembalian_materials', function (Blueprint $table) {
            $table->string('pic')->nullable()->after('tanggal_pengembalian');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pengembalian_materials', function (Blueprint $table) {
            $table->dropColumn('pic');
        });
    }
};
