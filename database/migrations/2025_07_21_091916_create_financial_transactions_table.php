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
        Schema::create('financial_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_type'); // نوع المعاملة (مثل: Revenue, Expense, Deposit, Withdrawal, Purchase)
            $table->foreignId('debit_account_id')->constrained('accounts')->onDelete('cascade'); // الحساب المدين
            $table->foreignId('credit_account_id')->constrained('accounts')->onDelete('cascade'); // الحساب الدائن
            $table->decimal('amount', 15, 2); // مبلغ المعاملة
            $table->string('source_type')->nullable(); // نوع المصدر (مثلاً: App\Models\Invoice)
            $table->unsignedBigInteger('source_id')->nullable(); // ID المصدر (مثلاً: ID الفاتورة)
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null'); // العميل/المورد المتعامل معه
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade'); // الشركة التابعة لها المعاملة
            $table->foreignId('cash_box_id')->nullable()->constrained('cash_boxes')->onDelete('set null'); // الصندوق النقدي المتأثر (إذا كانت المعاملة نقدية)
            $table->decimal('paid_amount', 15, 2)->default(0); // المبلغ المدفوع (للفواتير مثلاً)
            $table->decimal('remaining_amount', 15, 2)->default(0); // المبلغ المتبقي (للفواتير مثلاً)
            $table->string('payment_method')->nullable(); // طريقة الدفع (إذا كانت المعاملة تتضمن دفع)
            $table->text('note')->nullable(); // ملاحظات على المعاملة
            $table->date('transaction_date'); // تاريخ حدوث المعاملة
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null'); // من قام بإنشاء القيد
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('financial_transactions');
    }
};
