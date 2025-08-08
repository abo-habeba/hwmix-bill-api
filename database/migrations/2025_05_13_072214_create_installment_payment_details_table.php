<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// جدول تفاصيل مدفوعات الأقساط
return new class extends Migration {
    public function up(): void
    {
        Schema::create('installment_payment_details', function (Blueprint $table) {
            $table->id(); // رقم السطر
            // الربط بالدفعة العامة من جدول 'payments'
            $table->foreignId('payment_id')->constrained('payments')->onDelete('cascade'); // الدفعة الرئيسية
            $table->foreignId('installment_id')->constrained('installments')->onDelete('cascade'); // القسط الذي تم دفعه
            $table->decimal('amount_paid', 15, 2); // المبلغ المدفوع لهذا القسط من الدفعة
            $table->timestamps(); // التواريخ

            // التأكد من أن كل دفعة لا يمكن أن تغطي نفس القسط أكثر من مرة
            $table->unique(['payment_id', 'installment_id']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('installment_payment_details');
    }
};
