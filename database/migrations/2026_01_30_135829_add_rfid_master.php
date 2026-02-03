<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rfid_masters', function (Blueprint $table) {
            $table->id();
            $table->string('rfid_tag')->unique();
            $table->string('product_id');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->enum('scan_type', ['in', 'out'])->default('in');
            $table->string('route')->nullable();
            $table->string('address')->nullable();
            $table->string('packaging_type')->nullable();
            $table->integer('quantity')->default(1);
            $table->string('operator_name')->nullable();
            $table->integer('shift')->default(1);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rfid_masters');
    }
};
