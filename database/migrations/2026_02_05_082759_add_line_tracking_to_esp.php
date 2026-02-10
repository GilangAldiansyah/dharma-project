<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('esp32_devices', function (Blueprint $table) {
            $table->boolean('is_paused')->default(false)->after('error_b');
            $table->timestamp('paused_at')->nullable()->after('is_paused');
            $table->decimal('total_pause_seconds', 12, 2)->default(0)->after('paused_at');
        });
    }

    public function down()
    {
        Schema::table('esp32_devices', function (Blueprint $table) {
            $table->dropColumn(['is_paused', 'paused_at', 'total_pause_seconds']);
        });
    }
};
