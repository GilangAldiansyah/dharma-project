<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ng_reports', function (Blueprint $table) {
            $table->json('ng_types')
                  ->after('part_id')
                  ->nullable()
                  ->comment('Jenis NG: bisa multiple (fungsi, dimensi, tampilan)');
        });
    }

    public function down(): void
    {
        Schema::table('ng_reports', function (Blueprint $table) {
            $table->dropColumn('ng_types');
        });
    }
};
