<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add shift column to line_operations
        Schema::table('line_operations', function (Blueprint $table) {
            $table->integer('shift')->nullable()->after('status')
                ->comment('Shift (1=07:00-16:00, 2=21:00-05:00, 3=16:00-21:00)');
        });

        // Add shift column to maintenance_reports
        Schema::table('maintenance_reports', function (Blueprint $table) {
            $table->integer('shift')->nullable()->after('status')
                ->comment('Shift (1=07:00-16:00, 2=21:00-05:00, 3=16:00-21:00)');
        });

        // Add shift column to line_oee_records
        Schema::table('line_oee_records', function (Blueprint $table) {
            $table->integer('shift')->nullable()->after('period_type')
                ->comment('Shift (1=07:00-16:00, 2=21:00-05:00, 3=16:00-21:00)');
        });

        // Add shift column to esp32_production_histories
        Schema::table('esp32_production_histories', function (Blueprint $table) {
            $table->integer('shift')->nullable()->after('completion_status')
                ->comment('Shift (1=07:00-16:00, 2=21:00-05:00, 3=16:00-21:00)');
        });

        // Add shift column to esp32_logs
        Schema::table('esp32_logs', function (Blueprint $table) {
            $table->integer('shift')->nullable()->after('logged_at')
                ->comment('Shift (1=07:00-16:00, 2=21:00-05:00, 3=16:00-21:00)');
        });
    }

    public function down(): void
    {
        Schema::table('line_operations', function (Blueprint $table) {
            $table->dropColumn('shift');
        });

        Schema::table('maintenance_reports', function (Blueprint $table) {
            $table->dropColumn('shift');
        });

        Schema::table('line_oee_records', function (Blueprint $table) {
            $table->dropColumn('shift');
        });

        Schema::table('esp32_production_histories', function (Blueprint $table) {
            $table->dropColumn('shift');
        });

        Schema::table('esp32_logs', function (Blueprint $table) {
            $table->dropColumn('shift');
        });
    }
};
