<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dies_processes', function (Blueprint $table) {
            $table->integer('std_stroke')->default(0)->after('tonase');
            $table->integer('current_stroke')->default(0)->after('std_stroke');
            $table->date('last_mtc_date')->nullable()->after('current_stroke');
        });

        Schema::table('dies', function (Blueprint $table) {
            $table->dropColumn([
                'std_stroke',
                'freq_maintenance',
                'freq_maintenance_day',
                'current_stroke',
                'last_mtc_date',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('dies_processes', function (Blueprint $table) {
            $table->dropColumn(['std_stroke', 'current_stroke', 'last_mtc_date']);
        });

        Schema::table('dies', function (Blueprint $table) {
            $table->integer('std_stroke')->default(0);
            $table->string('freq_maintenance')->nullable();
            $table->integer('freq_maintenance_day')->nullable();
            $table->integer('current_stroke')->default(0);
            $table->date('last_mtc_date')->nullable();
        });
    }
};
