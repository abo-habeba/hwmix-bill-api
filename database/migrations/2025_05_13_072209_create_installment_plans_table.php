<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// جدول خطط التقسيط
return new class extends Migration {
    public function up(): void
    {
        Schema::create('installment_plans', function (Blueprint $table) {
            $table->id();  // رقم السطر
            $table->foreignId('invoice_id')->constrained('invoices')->onDelete('cascade');  // الفاتورة
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');  // العميل
            $table->foreignId('company_id')->constrained()->onDelete('cascade');  // الشركة
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');  // أنشئ بواسطة
            $table->decimal('total_amount', 15, 2);  // إجمالي المبلغ
            $table->decimal('down_payment', 15, 2)->default(0);  // الدفعة المقدمة
            $table->decimal('remaining_amount', 15, 2);  // المتبقي
            $table->integer('number_of_installments');  // عدد الأقساط
            $table->decimal('installment_amount', 15, 2);  // قيمة القسط
            $table->date('start_date');  // تاريخ البداية
            $table->date('end_date');  // تاريخ النهاية
            $table->string('status');  // الحالة
            $table->text('notes')->nullable();  // ملاحظات
            $table->timestamps();  // التواريخ
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('installment_plans');
    }
};
