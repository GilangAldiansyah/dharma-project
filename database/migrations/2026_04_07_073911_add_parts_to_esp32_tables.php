<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('esp32_parts', function (Blueprint $table) {
            $table->id();
            $table->string('device_id', 100);
            $table->string('part_id', 100);
            $table->string('part_name', 255)->nullable();
            $table->integer('max_count')->default(100);
            $table->integer('max_stroke')->default(0);
            $table->integer('cycle_time')->default(3);
            $table->timestamp('production_started_at')->nullable();
            $table->timestamps();

            $table->unique(['device_id', 'part_id']);
            $table->foreign('device_id')->references('device_id')->on('esp32_devices')->onDelete('cascade');
        });

        Schema::table('esp32_devices', function (Blueprint $table) {
            $table->string('active_part_id', 100)->nullable()->after('device_id');
        });

        Schema::table('esp32_logs', function (Blueprint $table) {
            $table->string('part_id', 100)->nullable()->after('device_id');
        });

        Schema::table('esp32_production_histories', function (Blueprint $table) {
            $table->string('part_id', 100)->nullable()->after('device_id');
            $table->string('part_name', 255)->nullable()->after('part_id');
        });
    }

    public function down(): void
    {
        Schema::table('esp32_production_histories', function (Blueprint $table) {
            $table->dropColumn(['part_id', 'part_name']);
        });

        Schema::table('esp32_logs', function (Blueprint $table) {
            $table->dropColumn('part_id');
        });

        Schema::table('esp32_devices', function (Blueprint $table) {
            $table->dropColumn('active_part_id');
        });

        Schema::dropIfExists('esp32_parts');
    }
};
