<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Suppliers Table
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('supplier_code')->unique();
            $table->string('supplier_name');
            $table->string('contact_person')->nullable();
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->timestamps();
        });

        // Parts Table
        Schema::create('parts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->onDelete('cascade');
            $table->string('part_code')->unique();
            $table->string('part_name');
            $table->json('product_images')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // NG Reports Table
        Schema::create('ng_reports', function (Blueprint $table) {
            $table->id();
            $table->string('report_number')->unique();
            $table->foreignId('part_id')->constrained()->onDelete('cascade');
            $table->json('ng_images')->nullable();
            $table->text('notes')->nullable();
            $table->string('reported_by');
            $table->timestamp('reported_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ng_reports');
        Schema::dropIfExists('parts');
        Schema::dropIfExists('suppliers');
    }
};
