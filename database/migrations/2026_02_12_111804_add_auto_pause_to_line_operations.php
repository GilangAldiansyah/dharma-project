<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('line_operations', function (Blueprint $table) {
            $table->json('pause_history')->nullable()->after('total_pause_minutes');
            $table->boolean('is_auto_paused')->default(false)->after('pause_history');
        });
    }

    public function down(): void
    {
        Schema::table('line_operations', function (Blueprint $table) {
            $table->dropColumn(['pause_history', 'is_auto_paused']);
        });
    }
};
