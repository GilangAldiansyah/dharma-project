<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('esp32_devices', function (Blueprint $table) {
            $table->id();
            $table->string('device_id')->unique();
            $table->integer('counter_a')->default(0);
            $table->integer('counter_b')->default(0);
            $table->integer('max_count')->default(10);
            $table->boolean('relay_status')->default(false);
            $table->boolean('error_b')->default(false);
            $table->timestamp('last_update')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('esp32_devices');
    }
};
