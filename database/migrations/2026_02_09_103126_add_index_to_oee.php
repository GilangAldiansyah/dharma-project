<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('esp32_production_histories', function (Blueprint $table) {
            $table->index(['device_id', 'production_started_at', 'production_finished_at'], 'idx_production_device_dates');
            $table->index('shift');
        });

        Schema::table('line_operations', function (Blueprint $table) {
            $table->index(['line_id', 'status', 'started_at', 'stopped_at'], 'idx_operations_line_status_dates');
            $table->index('shift');
        });

        Schema::table('maintenance_reports', function (Blueprint $table) {
            $table->index(['machine_id', 'status', 'completed_at'], 'idx_maintenance_machine_status_date');
            $table->index('shift');
        });
    }

    public function down(): void
    {
        Schema::table('esp32_production_histories', function (Blueprint $table) {
            $table->dropIndex('idx_production_device_dates');
            $table->dropIndex(['shift']);
        });

        Schema::table('line_operations', function (Blueprint $table) {
            $table->dropIndex('idx_operations_line_status_dates');
            $table->dropIndex(['shift']);
        });

        Schema::table('maintenance_reports', function (Blueprint $table) {
            $table->dropIndex('idx_maintenance_machine_status_date');
            $table->dropIndex(['shift']);
        });
    }
};
