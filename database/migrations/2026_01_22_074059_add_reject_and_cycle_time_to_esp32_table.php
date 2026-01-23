<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('esp32_devices', function (Blueprint $table) {
            $table->integer('reject')->default(0)->after('counter_b');
            $table->integer('cycle_time')->default(0)->after('reject');
            $table->integer('max_stroke')->default(0)->after('cycle_time');
            $table->integer('loading_time')->default(0)->after('max_stroke');
            $table->timestamp('production_started_at')->nullable()->after('loading_time');
        });

        Schema::table('esp32_logs', function (Blueprint $table) {
            $table->integer('reject')->default(0)->after('counter_b');
            $table->integer('cycle_time')->default(0)->after('reject');
            $table->integer('max_stroke')->default(0)->after('cycle_time');
            $table->integer('loading_time')->default(0)->after('max_stroke');
            $table->timestamp('production_started_at')->nullable()->after('loading_time');
        });

        DB::statement('UPDATE esp32_devices SET loading_time = max_count * cycle_time');
        DB::statement('UPDATE esp32_logs SET loading_time = max_count * cycle_time');
    }

    public function down(): void
    {
        Schema::table('esp32_devices', function (Blueprint $table) {
            $table->dropColumn(['reject', 'cycle_time', 'max_stroke', 'loading_time', 'production_started_at']);
        });

        Schema::table('esp32_logs', function (Blueprint $table) {
            $table->dropColumn(['reject', 'cycle_time', 'max_stroke', 'loading_time', 'production_started_at']);
        });
    }
};
