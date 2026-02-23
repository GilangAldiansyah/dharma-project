<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('esp32_devices', function (Blueprint $table) {
            $table->boolean('stop_operation')->default(false)->after('reset_requested');
            $table->boolean('line_stop_requested')->default(false)->after('stop_operation');
            $table->boolean('pause_requested')->default(false)->after('line_stop_requested');
            $table->boolean('resume_requested')->default(false)->after('pause_requested');
        });

        Schema::table('lines', function (Blueprint $table) {
            $table->boolean('pending_line_stop')->default(false)->after('last_line_stop');
        });
    }

    public function down(): void
    {
        Schema::table('esp32_devices', function (Blueprint $table) {
            $table->dropColumn([
                'stop_operation',
                'line_stop_requested',
                'pause_requested',
                'resume_requested',
            ]);
        });

        Schema::table('lines', function (Blueprint $table) {
            $table->dropColumn('pending_line_stop');
        });
    }
};
