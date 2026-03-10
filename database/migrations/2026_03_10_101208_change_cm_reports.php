<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cm_reports', function (Blueprint $table) {
            $table->dateTime('report_date')->change();
            $table->text('description')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('cm_reports', function (Blueprint $table) {
            $table->date('report_date')->change();
            $table->text('description')->nullable(false)->change();
        });
    }
};
