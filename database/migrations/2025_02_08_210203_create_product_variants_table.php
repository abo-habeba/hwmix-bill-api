<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->string('barcode')->unique()->nullable();
            $table->string('sku')->unique()->nullable();
            $table->decimal('purchase_price', 10, 2)->default(0);
            $table->decimal('wholesale_price', 10, 2)->default(0);
            $table->decimal('retail_price', 10, 2)->default(0);
            $table->integer('stock_threshold')->default(0)->default(0);
            $table->enum('status', ['active', 'inactive', 'discontinued'])->default('active');
            $table->date('expiry_date')->nullable();
            $table->string('image_url')->nullable();
            $table->decimal('weight', 8, 2)->nullable();
            $table->text('dimensions')->nullable();
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('discount', 8, 2)->nullable();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
