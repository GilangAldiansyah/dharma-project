<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dies_processes', function (Blueprint $table) {
            $table->unsignedInteger('no_proses')->default(0)->after('dies_id');
        });

        DB::statement('
            UPDATE dies_processes dp
            JOIN (
                SELECT id, ROW_NUMBER() OVER (PARTITION BY dies_id ORDER BY id) - 1 AS rn
                FROM dies_processes
            ) ranked ON dp.id = ranked.id
            SET dp.no_proses = ranked.rn
        ');
    }

    public function down(): void
    {
        Schema::table('dies_processes', function (Blueprint $table) {
            $table->dropColumn('no_proses');
        });
    }
};
