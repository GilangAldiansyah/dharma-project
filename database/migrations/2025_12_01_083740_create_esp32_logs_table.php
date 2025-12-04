<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('esp32_logs', function (Blueprint $table) {
            $table->id();
            $table->string('device_id');
            $table->integer('counter_a');
            $table->integer('counter_b');
            $table->integer('max_count');
            $table->boolean('relay_status');
            $table->boolean('error_b');
            $table->timestamp('logged_at');
            $table->timestamps();

            $table->index('device_id');
            $table->index('logged_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('esp32_logs');
    }
};
