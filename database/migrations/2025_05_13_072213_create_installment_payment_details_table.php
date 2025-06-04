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
            $table->foreignId('installment_payment_id')->constrained('installment_payments')->onDelete('cascade'); // مدفوعات القسط
            $table->foreignId('installment_id')->constrained('installments')->onDelete('cascade'); // القسط
            $table->decimal('amount_paid', 15, 2); // المبلغ المدفوع للقسط
            $table->timestamps(); // التواريخ
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('installment_payment_details');
    }
};
