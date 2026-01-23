<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('qr_code_token')->unique()->nullable()->after('password');
            $table->timestamp('qr_code_generated_at')->nullable()->after('qr_code_token');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['qr_code_token', 'qr_code_generated_at']);
        });
    }
};
