<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('esp32_devices', function (Blueprint $table) {
            $table->boolean('reset_requested')->default(false)->after('error_b');
            $table->time('schedule_start_time')->default('07:00:00')->after('total_pause_seconds');
            $table->time('schedule_end_time')->default('16:00:00')->after('schedule_start_time');
            $table->json('schedule_breaks')->nullable()->after('schedule_end_time');
        });
    }

    public function down()
    {
        Schema::table('esp32_devices', function (Blueprint $table) {
            $table->dropColumn(['schedule_start_time', 'schedule_end_time', 'schedule_breaks']);
            $table->dropColumn('reset_requested');
        });
    }
};
