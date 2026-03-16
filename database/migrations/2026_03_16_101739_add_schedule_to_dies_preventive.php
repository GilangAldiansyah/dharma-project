<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dies_preventive', function (Blueprint $table) {
            $table->enum('status', ['scheduled', 'pending', 'in_progress', 'completed'])
                ->default('pending')->change();
            $table->date('scheduled_date')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('dies_preventive', function (Blueprint $table) {
            $table->enum('status', ['pending', 'in_progress', 'completed'])
                ->default('pending')->change();
            $table->dropColumn('scheduled_date');
        });
    }
};
