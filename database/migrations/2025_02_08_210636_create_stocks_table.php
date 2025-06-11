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
        Schema::create('stocks', function (Blueprint $table) {
            $table->id();

            $table->integer('qty')->default(0);  // الكمية الحالية
            $table->integer('reserved')->default(0);  // الكمية المحجوزة
            $table->integer('min_qty')->default(0);  // الحد الأدنى قبل التنبيه (للتنبيه في حالة الانخفاض)

            $table->decimal('cost', 10, 2)->nullable();  // سعر الشراء للوحدة

            $table->string('batch')->nullable();  // رقم الدفعة
            $table->date('expiry')->nullable();  // تاريخ انتهاء الصلاحية

            $table->string('loc')->nullable();  // موقع داخل المخزن (رف مثلا)

            $table->enum('status', ['available', 'unavailable', 'expired'])->default('available');  // حالة الدفعة

            $table->foreignId('variant_id')->constrained('product_variants')->onDelete('cascade');  // المتغير
            $table->foreignId('warehouse_id')->constrained()->onDelete('cascade');  // المخزن

            $table->foreignId('company_id')->constrained()->onDelete('cascade');  // الشركة المالكة
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');  // أنشئ بواسطة
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('cascade');  // آخر من عدل

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stocks');
    }
};
