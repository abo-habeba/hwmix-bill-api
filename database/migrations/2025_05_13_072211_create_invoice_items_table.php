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

        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('invoice_id')->constrained('invoices')->onDelete('cascade');
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            // تم إزالة التكرار: يجب أن يكون هناك عمود واحد فقط لـ 'variant_id'
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->nullOnDelete();

            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade'); // تم تحديد اسم الجدول 'companies'
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->decimal('cost_price', 15, 2)->default(0); // سعر التكلفة للبند (موجود بالفعل)
            $table->string('name'); // اسم الصنف/الخدمة في الفاتورة
            $table->decimal('quantity', 15, 2); // الكمية
            $table->decimal('unit_price', 15, 2); // سعر الوحدة
            $table->decimal('discount', 15, 2)->default(0); // الخصم على البند
            $table->decimal('total', 15, 2); // الإجمالي للبند

            $table->timestamps();
            $table->softDeletes(); // دعم soft delete
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
    }
};
