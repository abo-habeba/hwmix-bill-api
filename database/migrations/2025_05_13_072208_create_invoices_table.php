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
        Schema::create('invoices', function (Blueprint $table) {
            // المعرف الأساسي
            $table->id();
            // رقم الفاتورة الفريد
            $table->string('invoice_number')->unique()->nullable();
            // تاريخ الاستحقاق
            $table->date('due_date')->nullable();
            // المبلغ الإجمالي
            $table->decimal('gross_amount', 15, 2);
            // إجمالي الخصم
            $table->decimal('total_discount', 15, 2)->default(0);
            // صافي المبلغ
            $table->decimal('net_amount', 15, 2);
            // المبلغ المدفوع
            $table->decimal('paid_amount', 15, 2)->default(0);
            // المبلغ المتبقي
            $table->decimal('remaining_amount', 15, 2)->default(0);

            // إضافة حقل الربح التقديري الإجمالي للفاتورة
            $table->decimal('estimated_profit', 15, 2)->default(0)->after('total_discount');

            // حالة الفاتورة (تم إضافة 'partially_paid' و 'paid')
            $table->enum('status', ['draft', 'confirmed', 'canceled', 'partially_paid', 'paid'])->default('confirmed');
            // ملاحظات
            $table->text('notes')->nullable();

            // معرف الشركة
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade'); // تم تحديد اسم الجدول
            // معرف المنشئ
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            // معرف آخر معدل
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            // معرف المستخدم
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade'); // تم تحديد اسم الجدول
            // معرف نوع الفاتورة
            $table->foreignId('invoice_type_id')->constrained('invoice_types')->onDelete('cascade'); // تم تحديد اسم الجدول
            // معرف صندوق النقد
            $table->foreignId('cash_box_id')->nullable()->constrained('cash_boxes')->nullOnDelete();
            // كود نوع الفاتورة
            $table->string('invoice_type_code')->nullable();

            // خطوة التقريب
            $table->integer('round_step')->nullable();

            // أختام الوقت (تاريخ الإنشاء والتحديث)
            $table->timestamps();
            // حذف ناعم (Soft Delete)
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
