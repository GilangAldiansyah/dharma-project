<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('die_shop_reports', function (Blueprint $table) {
            $table->enum('shift', ['1', '2'])->after('pic_name');

            // Hapus kolom activity_type jika ada
            if (Schema::hasColumn('die_shop_reports', 'activity_type')) {
                $table->dropColumn('activity_type');
            }
        });
    }

    public function down()
    {
        Schema::table('die_shop_reports', function (Blueprint $table) {
            $table->dropColumn('shift');
            $table->enum('activity_type', ['corrective', 'preventive'])->after('report_no');
        });
    }
};
