<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// جدول المدفوعات
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id(); // رقم السطر
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // المستخدم
            $table->foreignId('company_id')->constrained()->onDelete('cascade'); // الشركة
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade'); // أنشئ بواسطة
            $table->date('payment_date'); // تاريخ الدفع
            $table->decimal('amount', 15, 2); // المبلغ
            $table->string('method'); // طريقة الدفع
            $table->text('notes')->nullable(); // ملاحظات
            $table->boolean('is_split')->default(false); // هل الدفع مجزأ
            $table->timestamps(); // التواريخ
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};