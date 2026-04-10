<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dies_io', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            $table->string('cc', 20)->nullable();
            $table->string('io_number', 20)->nullable();
            $table->string('keterangan')->nullable();
            $table->timestamps();
        });
        Schema::table('dies_history_sparepart', function (Blueprint $table) {
            $table->unsignedBigInteger('io_id')->nullable()->after('dies_id');
            $table->foreign('io_id')->references('id')->on('dies_io')->nullOnDelete();
        });
        Schema::table('dies', function (Blueprint $table) {
            $table->string('customer')->nullable()->after('line');
        });
    }
    public function down(): void
    {
        Schema::table('dies', function (Blueprint $table) {
            $table->dropColumn('customer');
        });
        Schema::table('dies_history_sparepart', function (Blueprint $table) {
            $table->dropForeign(['io_id']);
            $table->dropColumn('io_id');
        });
        Schema::dropIfExists('dies_io');
    }
};
