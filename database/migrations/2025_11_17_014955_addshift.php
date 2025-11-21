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
        Schema::table('output_products', function (Blueprint $table) {
            $table->integer('out_shift3')->default(0)->after('out_shift2');
            $table->integer('ng_shift3')->default(0)->after('ng_shift2');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('output_products', function (Blueprint $table) {
            $table->dropColumn(['out_shift3', 'ng_shift3']);
        });
    }
};
