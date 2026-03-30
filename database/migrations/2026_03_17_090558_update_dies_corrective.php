<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dies_corrective', function (Blueprint $table) {
            $table->foreignId('process_id')
                ->nullable()
                ->after('dies_id')
                ->constrained('dies_processes')
                ->nullOnDelete();
            $table->unsignedInteger('repair_duration_minutes')
                ->nullable()
                ->after('stroke_at_maintenance');
        });
        $rows = DB::table('dies_corrective')->whereNotNull('photos')->get(['id', 'photos']);
        foreach ($rows as $row) {
            $photos = json_decode($row->photos, true);
            if (!is_array($photos) || empty($photos)) continue;
            if (isset($photos[0]['path'])) continue;
            $newPhotos = array_map(fn($p) => ['path' => $p, 'type' => 'before'], $photos);
            DB::table('dies_corrective')->where('id', $row->id)->update([
                'photos' => json_encode($newPhotos),
            ]);
        }
        Schema::table('dies_corrective', function (Blueprint $table) {
            $table->dateTime('report_date')->change();
            $table->text('problem_description')->nullable()->change();
            $table->text('cause')->nullable()->change();
            $table->text('repair_action')->nullable()->change();
        });
        Schema::table('dies_preventive', function (Blueprint $table) {
            $table->foreignId('process_id')
                ->nullable()
                ->after('dies_id')
                ->constrained('dies_processes')
                ->nullOnDelete();
            $table->string('pic_dies')->nullable()->after('photos');
            $table->enum('condition', ['ok', 'nok'])->nullable()->after('pic_dies');
            $table->unsignedBigInteger('nok_closed_by')->nullable()->after('condition');
            $table->timestamp('nok_closed_at')->nullable()->after('nok_closed_by');
            $table->text('nok_notes')->nullable()->after('nok_closed_at');
            $table->foreign('nok_closed_by')->references('id')->on('users')->nullOnDelete();
        });
    }
    public function down(): void
    {
        Schema::table('dies_corrective', function (Blueprint $table) {
            if (Schema::hasColumn('dies_corrective', 'process_id')) {
                $table->dropForeign(['process_id']);
                $table->dropColumn('process_id');
            }
            if (Schema::hasColumn('dies_corrective', 'repair_duration_minutes')) {
                $table->dropColumn('repair_duration_minutes');
            }
            $table->date('report_date')->change();
        });
        Schema::table('dies_preventive', function (Blueprint $table) {
            if (Schema::hasColumn('dies_preventive', 'process_id')) {
                $table->dropForeign(['process_id']);
                $table->dropColumn('process_id');
            }
            if (Schema::hasColumn('dies_preventive', 'nok_closed_by')) {
                $table->dropForeign(['nok_closed_by']);
                $table->dropColumn('nok_closed_by');
            }
            if (Schema::hasColumn('dies_preventive', 'pic_dies')) $table->dropColumn('pic_dies');
            if (Schema::hasColumn('dies_preventive', 'condition')) $table->dropColumn('condition');
            if (Schema::hasColumn('dies_preventive', 'nok_closed_at')) $table->dropColumn('nok_closed_at');
            if (Schema::hasColumn('dies_preventive', 'nok_notes')) $table->dropColumn('nok_notes');
        });
    }
};
