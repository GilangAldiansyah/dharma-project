<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ng_reports', function (Blueprint $table) {
            $table->json('temporary_actions')->nullable()->after('ng_types');
            $table->text('temporary_action_notes')->nullable()->after('temporary_actions');
            $table->timestamp('ta_submitted_at')->nullable()->after('temporary_action_notes');
            $table->string('ta_submitted_by')->nullable()->after('ta_submitted_at');
            $table->enum('ta_status', ['submitted', 'approved', 'rejected'])->nullable()->after('ta_submitted_by');
            $table->timestamp('ta_reviewed_at')->nullable()->after('ta_status');
            $table->string('ta_reviewed_by')->nullable()->after('ta_reviewed_at');
            $table->text('ta_rejection_reason')->nullable()->after('ta_reviewed_by');

            $table->enum('pica_status', ['submitted', 'approved', 'rejected'])->nullable()->after('pica_uploaded_by');
            $table->timestamp('pica_reviewed_at')->nullable()->after('pica_status');
            $table->string('pica_reviewed_by')->nullable()->after('pica_reviewed_at');
            $table->text('pica_rejection_reason')->nullable()->after('pica_reviewed_by');
        });
    }

    public function down(): void
    {
        Schema::table('ng_reports', function (Blueprint $table) {
            $table->dropColumn([
                'temporary_actions',
                'temporary_action_notes',
                'ta_submitted_at',
                'ta_submitted_by',
                'ta_status',
                'ta_reviewed_at',
                'ta_reviewed_by',
                'ta_rejection_reason',
                'pica_status',
                'pica_reviewed_at',
                'pica_reviewed_by',
                'pica_rejection_reason',
            ]);
        });
    }
};
