<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('output_product_materials', function (Blueprint $table) {
            $table->integer('qty_per_unit')->default(1)->after('sap_no');
        });
    }

    public function down()
    {
        Schema::table('output_product_materials', function (Blueprint $table) {
            $table->dropColumn('qty_per_unit');
        });
    }
};
