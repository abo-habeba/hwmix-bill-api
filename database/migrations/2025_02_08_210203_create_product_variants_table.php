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

            $table->string('barcode')->unique()->nullable();  // باركود
            $table->string('sku')->unique()->nullable();  // رمز التخزين الداخلي

            $table->decimal('retail_price', 10, 2)->nullable();  // سعر البيع قطاعي
            $table->decimal('wholesale_price', 10, 2)->nullable();  // سعر البيع جملة
            $table->decimal('profit_margin', 5, 2)->nullable();  //  هامش الربح
            $table->integer('min_quantity')->nullable();  //  الحد الأدنى للكمية

            $table->string('image')->nullable();  // صورة المتغير
            $table->decimal('weight', 8, 2)->nullable();  // الوزن
            $table->string('dimensions')->nullable();  // الأبعاد

            $table->decimal('tax', 5, 2)->nullable();  // نسبة الضريبة
            $table->decimal('discount', 8, 2)->nullable();  // الخصم

            $table->enum('status', ['active', 'inactive', 'discontinued'])->default('active');  // حالة المتغير

            $table->foreignId('product_id')->constrained()->onDelete('cascade');  // مرتبط بمنتج
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');  // أنشئ بواسطة
            $table->foreignId('company_id')->constrained()->onDelete('cascade');  // الشركة المالكة

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
