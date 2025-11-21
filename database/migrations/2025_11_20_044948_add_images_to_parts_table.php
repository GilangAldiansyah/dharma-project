<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ng_reports', function (Blueprint $table) {
            $table->json('ng_images')->nullable()->after('ng_image');
        });
    }

    public function down(): void
    {
        Schema::table('ng_reports', function (Blueprint $table) {
            $table->dropColumn('ng_images');
        });
    }
};
