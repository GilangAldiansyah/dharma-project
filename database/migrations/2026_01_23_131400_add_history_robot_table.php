<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('esp32_production_histories', function (Blueprint $table) {
            $table->id();
            $table->string('device_id', 100)->index();
            $table->integer('total_counter_a')->default(0);
            $table->integer('total_counter_b')->default(0);
            $table->integer('total_reject')->default(0);
            $table->integer('cycle_time');
            $table->integer('max_count');
            $table->integer('max_stroke')->default(0);
            $table->integer('expected_time_seconds');
            $table->integer('actual_time_seconds');
            $table->integer('delay_seconds');
            $table->timestamp('production_started_at')->nullable();
            $table->timestamp('production_finished_at')->nullable();
            $table->string('completion_status', 50);
            $table->timestamps();

            $table->foreign('device_id')
                ->references('device_id')
                ->on('esp32_devices')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('esp32_production_histories');
    }
};
