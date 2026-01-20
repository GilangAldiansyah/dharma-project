<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // MACHINES TABLE
        Schema::table('machines', function (Blueprint $table) {
            $table->boolean('is_archived')->default(false)->after('id');
            $table->date('period_start')->nullable()->after('is_archived');
            $table->date('period_end')->nullable()->after('period_start');
            $table->foreignId('parent_machine_id')->nullable()->after('period_end')
                  ->constrained('machines')->onDelete('cascade');

            $table->decimal('total_operation_hours', 10, 4)->default(0)->after('machine_type'); // ✅ Ubah ke 4 desimal
            $table->decimal('total_repair_hours', 10, 4)->default(0)->after('total_operation_hours'); // ✅ Ubah ke 4 desimal
            $table->integer('total_failures')->default(0)->after('total_repair_hours');
            $table->decimal('mttr_hours', 10, 4)->nullable()->after('total_failures'); // ✅ Ubah ke 4 desimal
            $table->decimal('mtbf_hours', 10, 4)->nullable()->after('mttr_hours'); // ✅ Ubah ke 4 desimal
            $table->timestamp('current_period_start')->nullable()->after('mtbf_hours');
        });

        Schema::table('lines', function (Blueprint $table) {
            $table->boolean('is_archived')->default(false)->after('id');
            $table->date('period_start')->nullable()->after('is_archived');
            $table->date('period_end')->nullable()->after('period_start');
            $table->foreignId('parent_line_id')->nullable()->after('period_end')
                  ->constrained('lines')->onDelete('cascade');

            $table->decimal('total_operation_hours', 10, 4)->default(0)->after('description'); // ✅ Ubah ke 4 desimal
            $table->decimal('total_repair_hours', 10, 4)->default(0)->after('total_operation_hours');
            $table->integer('total_failures')->default(0)->after('total_repair_hours');
            $table->timestamp('current_period_start')->nullable()->after('last_line_stop');
        });

        Schema::table('lines', function (Blueprint $table) {
            $table->enum('status', ['operating', 'stopped', 'maintenance', 'paused'])
                ->default('stopped')
                ->change();
        });

        Schema::table('line_operations', function (Blueprint $table) {
            $table->timestamp('paused_at')->nullable()->after('stopped_at');
            $table->timestamp('resumed_at')->nullable()->after('paused_at');

            $table->decimal('total_pause_minutes', 10, 4)->default(0)->after('resumed_at');
            $table->decimal('duration_minutes', 10, 4)->nullable()->change();
            $table->decimal('mtbf_hours', 10, 4)->nullable()->change();
        });

        Schema::table('line_operations', function (Blueprint $table) {
            $table->enum('status', ['running', 'paused', 'stopped'])->default('running')->change();
        });

        Schema::table('maintenance_reports', function (Blueprint $table) {
            $table->decimal('repair_duration_minutes', 10, 4)->nullable()->change();
            $table->decimal('line_stop_duration_minutes', 10, 4)->nullable()->change();
        });

        if (Schema::hasColumn('lines', 'operational_started_at')) {
            Schema::table('lines', function (Blueprint $table) {
                $table->dropColumn([
                    'operational_started_at',
                    'total_operational_seconds',
                    'total_repair_duration_minutes'
                ]);
            });
        }
    }

    public function down(): void
    {
        Schema::table('machines', function (Blueprint $table) {
            if (Schema::hasColumn('machines', 'parent_machine_id')) {
                $table->dropForeign(['parent_machine_id']);
            }
        });

        Schema::table('lines', function (Blueprint $table) {
            if (Schema::hasColumn('lines', 'parent_line_id')) {
                $table->dropForeign(['parent_line_id']);
            }
        });

        Schema::table('machines', function (Blueprint $table) {
            $table->dropColumn([
                'is_archived',
                'period_start',
                'period_end',
                'parent_machine_id',
                'total_operation_hours',
                'total_repair_hours',
                'total_failures',
                'mttr_hours',
                'mtbf_hours',
                'current_period_start'
            ]);
        });

        Schema::table('lines', function (Blueprint $table) {
            $table->dropColumn([
                'is_archived',
                'period_start',
                'period_end',
                'parent_line_id',
                'total_operation_hours',
                'total_repair_hours',
                'total_failures',
                'current_period_start'
            ]);
        });

        Schema::table('lines', function (Blueprint $table) {
            $table->enum('status', ['operating', 'stopped', 'maintenance'])
                ->default('stopped')
                ->change();
        });

        Schema::table('line_operations', function (Blueprint $table) {
            $table->dropColumn(['paused_at', 'resumed_at']);
            $table->integer('total_pause_minutes')->default(0)->change();
            $table->integer('duration_minutes')->nullable()->change();
            $table->decimal('mtbf_hours', 10, 2)->nullable()->change();
        });

        Schema::table('line_operations', function (Blueprint $table) {
            $table->enum('status', ['running', 'stopped'])->default('running')->change();
        });

        Schema::table('maintenance_reports', function (Blueprint $table) {
            $table->integer('repair_duration_minutes')->nullable()->change();
            $table->integer('line_stop_duration_minutes')->nullable()->change();
        });

        Schema::table('lines', function (Blueprint $table) {
            $table->timestamp('operational_started_at')->nullable();
            $table->integer('total_operational_seconds')->default(0);
            $table->integer('total_repair_duration_minutes')->default(0);
        });
    }
};
