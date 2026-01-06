<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('machines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('line_id')->nullable()->constrained('lines')->onDelete('cascade');
            $table->string('machine_name', 100);
            $table->string('barcode', 100)->unique();
            $table->string('plant', 50);
            $table->string('line', 50); // Tetap ada untuk backward compatibility
            $table->string('machine_type', 50)->nullable();
            $table->timestamps();

            $table->index('line_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('machines');
    }
};
