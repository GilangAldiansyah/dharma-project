<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('output_product_materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('output_product_id')->constrained()->onDelete('cascade');
            $table->string('sap_no'); // Material Number dari Control Stock
            $table->timestamps();

            // 1 output product tidak bisa punya SAP yang sama 2x
            $table->unique(['output_product_id', 'sap_no']);
            $table->index('sap_no'); // Index untuk faster search
        });
    }

    public function down()
    {
        Schema::dropIfExists('output_product_materials');
    }
};
