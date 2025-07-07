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
            $table->id();

            $table->string('invoice_number')->unique()->nullable();
            $table->date('due_date')->nullable();

            $table->decimal('gross_amount', 15, 2);      // المبلغ قبل الخصم
            $table->decimal('total_discount', 15, 2);    // إجمالي الخصم
            $table->decimal('net_amount', 15, 2);        // المبلغ بعد الخصم
            $table->decimal('paid_amount', 15, 2);       // المدفوع
            $table->decimal('remaining_amount', 15, 2);  // المتبقي

            $table->string('status'); // draft, confirmed
            $table->text('notes')->nullable();

            // علاقات
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade'); // من أنشأ الفاتورة
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // العميل
            $table->foreignId('invoice_type_id')->constrained()->onDelete('cascade');
            $table->foreignId('cash_box_id')->nullable()->constrained()->onDelete('set null');
            $table->string('invoice_type_code')->nullable();

            $table->integer('round_step')->nullable(); // خطوة التقريب إن وجدت

            $table->timestamps();
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
