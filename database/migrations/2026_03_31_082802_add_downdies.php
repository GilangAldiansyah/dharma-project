<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dies_corrective', function (Blueprint $table) {
            $table->timestamp('off_machine_at')->nullable()->after('closed_at');
            $table->integer('machine_duration_minutes')->nullable()->after('off_machine_at');
        });
        DB::statement("ALTER TABLE dies_corrective MODIFY COLUMN status ENUM('open', 'in_progress', 'on_repair', 'closed') NOT NULL DEFAULT 'open'");

        DB::statement("ALTER TABLE dies_history_sparepart MODIFY COLUMN tipe ENUM('preventive', 'corrective', 'reguler', 'masuk') NOT NULL");
    }

    public function down(): void
    {
        Schema::table('dies_corrective', function (Blueprint $table) {
            $table->dropColumn(['off_machine_at', 'machine_duration_minutes']);
        });
        DB::statement("ALTER TABLE dies_corrective MODIFY COLUMN status ENUM('open', 'in_progress', 'closed') NOT NULL DEFAULT 'open'");
        DB::statement("ALTER TABLE dies_history_sparepart MODIFY COLUMN tipe ENUM('preventive', 'corrective', 'reguler') NOT NULL");
    }
};
