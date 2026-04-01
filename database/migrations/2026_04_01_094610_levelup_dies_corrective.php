<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dies_corrective_repair_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('corrective_id')->constrained('dies_corrective')->cascadeOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->integer('duration_minutes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('dies_corrective', function (Blueprint $table) {
            $table->timestamp('repair_started_at')->nullable()->after('off_machine_at');
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('dies_corrective_repair_sessions');

        Schema::table('dies_corrective', function (Blueprint $table) {
            $table->dropColumn('repair_started_at');
        });
    }
};
