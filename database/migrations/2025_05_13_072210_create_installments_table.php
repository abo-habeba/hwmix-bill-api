<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// جدول الأقساط
return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('installments', function (Blueprint $table) {
            $table->id();  // رقم السطر
            $table->foreignId('installment_plan_id')->constrained('installment_plans')->onDelete('cascade');  // خطة التقسيط
            $table->string('installment_number');
            $table->date('due_date');  // تاريخ الاستحقاق
            $table->decimal('amount', 15, 2);  // قيمة القسط
            $table->string('status');  // حالة القسط
            $table->timestamp('paid_at')->nullable();  // تاريخ السداد
            $table->decimal('remaining', 15, 2)->default(0);  // المتبقي
            $table->timestamps();  // التواريخ
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('installments');
    }
};
