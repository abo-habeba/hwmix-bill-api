<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// جدول مدفوعات الأقساط
return new class extends Migration {
    public function up(): void
    {
        Schema::create('installment_payments', function (Blueprint $table) {
            $table->id(); // رقم السطر
            $table->foreignId('installment_plan_id')->constrained('installment_plans')->onDelete('cascade'); // خطة التقسيط
            $table->foreignId('company_id')->constrained()->onDelete('cascade'); // الشركة
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade'); // أنشئ بواسطة
            $table->date('payment_date'); // تاريخ الدفع
            $table->decimal('amount_paid', 15, 2); // المبلغ المدفوع
            $table->foreignId('cash_box_id')->nullable()->constrained('cash_boxes')->onDelete('set null');
            $table->string('payment_method'); // طريقة الدفع
            $table->text('notes')->nullable(); // ملاحظات
            $table->timestamps(); // التواريخ
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('installment_payments');
    }
};
