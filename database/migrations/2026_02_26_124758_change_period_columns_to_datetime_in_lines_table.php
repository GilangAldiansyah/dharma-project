<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lines', function (Blueprint $table) {
            $table->dateTime('period_start')->nullable()->change();
            $table->dateTime('period_end')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('lines', function (Blueprint $table) {
            $table->date('period_start')->nullable()->change();
            $table->date('period_end')->nullable()->change();
        });
    }
};
