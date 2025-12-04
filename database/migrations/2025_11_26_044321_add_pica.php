<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ng_reports', function (Blueprint $table) {
            $table->string('pica_document')->nullable()->after('notes');
            $table->timestamp('pica_uploaded_at')->nullable()->after('pica_document');
            $table->string('pica_uploaded_by')->nullable()->after('pica_uploaded_at');
            $table->enum('status', ['open', 'pica_submitted', 'closed'])->default('open')->after('pica_uploaded_by');
        });
    }

    public function down(): void
    {
        Schema::table('ng_reports', function (Blueprint $table) {
            $table->dropColumn(['pica_document', 'pica_uploaded_at', 'pica_uploaded_by', 'status']);
        });
    }
};
