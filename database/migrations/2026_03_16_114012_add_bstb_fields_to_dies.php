<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dies', function (Blueprint $table) {
            $table->timestamp('bstb_updated_at')->nullable()->after('last_mtc_date');
        });
    }

    public function down(): void
    {
        Schema::table('dies', function (Blueprint $table) {
            $table->dropColumn(['total_stroke', 'bstb_updated_at']);
        });
    }
};
