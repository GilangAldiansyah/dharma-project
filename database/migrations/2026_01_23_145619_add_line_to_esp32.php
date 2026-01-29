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
        Schema::table('esp32_devices', function (Blueprint $table) {
            $table->unsignedBigInteger('line_id')->nullable()->after('device_id');

            $table->foreign('line_id')
                ->references('id')
                ->on('lines')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('esp32_devices', function (Blueprint $table) {
            $table->dropForeign(['line_id']);
            $table->dropColumn('line_id');
        });
    }
};
