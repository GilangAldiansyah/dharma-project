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
        Schema::create('line_oee_records', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('line_id');
            $table->string('period_type')->default('daily'); // daily, weekly, monthly, custom
            $table->date('period_date'); // untuk daily/weekly/monthly reference
            $table->dateTime('period_start');
            $table->dateTime('period_end');

            // Operation Time Metrics (in hours)
            $table->decimal('operation_time_hours', 10, 4)->default(0);
            $table->decimal('uptime_hours', 10, 4)->default(0);
            $table->decimal('downtime_hours', 10, 4)->default(0);

            // Production Metrics
            $table->integer('total_counter_a')->default(0);
            $table->integer('target_production')->default(0);
            $table->integer('total_reject')->default(0);
            $table->integer('good_count')->default(0);
            $table->decimal('avg_cycle_time', 10, 4)->default(0); // in seconds

            // OEE Components (in percentage)
            $table->decimal('availability', 5, 2)->default(0); // 0-100
            $table->decimal('performance', 5, 2)->default(0); // 0-100
            $table->decimal('quality', 5, 2)->default(0);
            $table->decimal('achievement_rate', 5, 2)->default(0);

            // Overall OEE (in percentage)
            $table->decimal('oee', 5, 2)->default(0); // 0-100

            // Additional Info
            $table->integer('total_failures')->default(0);
            $table->text('notes')->nullable();
            $table->string('calculated_by')->nullable();

            $table->timestamps();

            // Indexes
            $table->foreign('line_id')->references('id')->on('lines')->onDelete('cascade');
            $table->index(['line_id', 'period_date']);
            $table->index(['line_id', 'period_type']);
            $table->index(['period_start', 'period_end']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('line_oee_records');
    }
};
