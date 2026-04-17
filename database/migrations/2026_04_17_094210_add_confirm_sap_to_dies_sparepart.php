<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dies_history_sparepart', function (Blueprint $table) {
            $table->timestamp('sap_confirmed_at')->nullable()->after('notes');
            $table->foreignId('sap_confirmed_by')->nullable()->constrained('users')->nullOnDelete()->after('sap_confirmed_at');
        });
    }

    public function down(): void
    {
        Schema::table('dies_history_sparepart', function (Blueprint $table) {
            $table->dropForeign(['sap_confirmed_by']);
            $table->dropColumn(['sap_confirmed_at', 'sap_confirmed_by']);
        });
    }
};
