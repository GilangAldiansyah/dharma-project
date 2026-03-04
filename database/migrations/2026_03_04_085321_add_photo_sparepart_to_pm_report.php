<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pm_reports', function (Blueprint $table) {
            $table->string('photo_sparepart')->nullable()->after('photo')->comment('Foto penggantian sparepart');
            $table->foreignId('nok_closed_by')->nullable()->after('condition')->constrained('users')->onDelete('restrict');
            $table->timestamp('nok_closed_at')->nullable()->after('nok_closed_by');
        });

        Schema::table('spareparts', function (Blueprint $table) {
            $table->string('sap_id', 50)->nullable()->unique()->after('id')->comment('ID SAP');
        });
    }

    public function down(): void
    {
        Schema::table('pm_reports', function (Blueprint $table) {
            $table->dropConstrainedForeignId('nok_closed_by');
            $table->dropColumn(['photo_sparepart', 'nok_closed_at']);
        });
    }
};
