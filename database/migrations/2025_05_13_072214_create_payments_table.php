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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade'); // المستخدم (الدافع/المستلم)
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade'); // الشركة التابعة للدفعة
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade'); // المستخدم الذي أنشأ سجل الدفعة
            $table->date('payment_date'); // تاريخ الدفع الفعلي

            $table->string('payment_type'); // 'inflow' (واردة) أو 'outflow' (صادرة)
            $table->decimal('amount', 15, 2); // المبلغ الكلي لهذه الدفعة
            $table->foreignId('cash_box_id')->nullable()->constrained('cash_boxes')->onDelete('set null'); // الصندوق النقدي المتأثر بالدفعة
            $table->string('method'); // طريقة الدفع (نقد، حوالة بنكية، بطاقة ائتمان)

            // علاقة Polymorphic لتحديد الكيان الذي تم الدفع لأجله (فاتورة، قسط، إلخ)
            $table->string('payable_type')->nullable();
            $table->unsignedBigInteger('payable_id')->nullable();
            $table->index(['payable_type', 'payable_id']); // فهرس لتحسين الأداء

            $table->text('notes')->nullable(); // ملاحظات إضافية على الدفعة
            $table->boolean('is_split')->default(false); // هل الدفعة مجزأة (جزئية)

            // ربط الدفعة بالقيد المحاسبي في جدول financial_transactions
            $table->foreignId('financial_transaction_id')->nullable()->constrained('financial_transactions')->onDelete('set null');
            // إضافة الحقل الجديد لـ 'realized_profit_amount'
            $table->decimal('realized_profit_amount', 15, 2)->default(0)->after('amount'); // الربح الفعلي المحقق من الدفعة

            $table->timestamps();
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
