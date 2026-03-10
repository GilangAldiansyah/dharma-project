<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('esp32_devices', function (Blueprint $table) {
            $table->json('schedules')->nullable()->after('schedule_breaks');
        });
    }

    public function down()
    {
        Schema::table('esp32_devices', function (Blueprint $table) {
            $table->dropColumn('schedules');
        });
    }
};
