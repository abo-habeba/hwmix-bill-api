<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// جدول الأرباح
return new class extends Migration {
    public function up(): void
    {
        Schema::create('profits', function (Blueprint $table) {
            $table->id(); // رقم السطر
            $table->string('source_type'); // نوع المصدر
            $table->unsignedBigInteger('source_id'); // رقم المصدر
            $table->unsignedBigInteger('created_by'); // أنشئ بواسطة
            $table->unsignedBigInteger('customer_id')->nullable(); // العميل
            $table->unsignedBigInteger('company_id'); // الشركة
            $table->decimal('revenue_amount', 15, 2); // الإيراد
            $table->decimal('cost_amount', 15, 2); // التكلفة
            $table->decimal('profit_amount', 15, 2); // الربح
            $table->string('note')->nullable(); // ملاحظة
            $table->date('profit_date'); // تاريخ الربح
            $table->timestamps(); // التواريخ

            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('customer_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('profits');
    }
};