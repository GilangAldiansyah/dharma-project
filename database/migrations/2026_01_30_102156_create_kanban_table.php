<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('line_id')->constrained()->onDelete('cascade');
            $table->string('product_code')->unique();
            $table->string('product_name');
            $table->string('customer')->nullable();
            $table->integer('current_stock')->default(0);
            $table->timestamps();
            $table->index('line_id');
            $table->index('product_code');
        });

        Schema::create('kanbans', function (Blueprint $table) {
            $table->id();
            $table->string('rfid_tag');
            $table->string('product_id');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->string('kanban_no')->unique();
            $table->enum('scan_type', ['in', 'out']);
            $table->string('route')->nullable();
            $table->string('address')->nullable();
            $table->string('packaging_type')->nullable();
            $table->integer('quantity');
            $table->timestamp('scanned_at');
            $table->string('operator_name')->nullable();
            $table->integer('shift')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index('rfid_tag');
            $table->index('product_id');
            $table->index('scanned_at');
            $table->index('scan_type');
            $table->index('kanban_no');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kanbans');
        Schema::dropIfExists('products');
    }
};
