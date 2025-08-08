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
        // 1. حذف المفاتيح الأجنبية التي تشير إلى الجداول التي سيتم حذفها أولاً
        // (من installment_payment_details إلى installment_payments)

        // 2. حذف الجداول القديمة
        Schema::dropIfExists('installment_payments');
        Schema::dropIfExists('payment_installment');
        Schema::dropIfExists('revenues');
        Schema::dropIfExists('profits');

        // 3. تعديل جدول 'payments' (إضافة حقول جديدة وتعديلات سابقة)
        Schema::table('payments', function (Blueprint $table) {
            // التعديلات السابقة
            $table->string('payment_type')->after('method')->default('inflow'); // 'inflow' أو 'outflow'
            $table->foreignId('cash_box_id')->nullable()->constrained('cash_boxes')->onDelete('set null')->after('amount');
            $table->foreignId('financial_transaction_id')->nullable()->constrained('financial_transactions')->onDelete('set null')->after('cash_box_id');
            $table->string('payable_type')->nullable()->after('financial_transaction_id');
            $table->unsignedBigInteger('payable_id')->nullable()->after('payable_type');
            $table->index(['payable_type', 'payable_id']);

            // إضافة الحقل الجديد لـ 'realized_profit_amount'
            $table->decimal('realized_profit_amount', 15, 2)->default(0)->after('amount'); // الربح الفعلي المحقق من الدفعة
        });

        // 4. تعديل جدول 'installment_payment_details' (تعديلات سابقة)
        Schema::table('installment_payment_details', function (Blueprint $table) {
            // حذف العمود القديم بعد حذف المفتاح الأجنبي
            if (Schema::hasColumn('installment_payment_details', 'installment_payment_id')) {
                $table->dropColumn('installment_payment_id');
            }

            // إضافة المفتاح الخارجي الجديد الذي يشير إلى جدول 'payments'
            $table->foreignId('payment_id')->constrained('payments')->onDelete('cascade')->after('id');

            // إضافة قيد فريد لضمان أن كل دفعة لا يمكن أن تغطي نفس القسط أكثر من مرة
            $table->unique(['payment_id', 'installment_id']);
        });

        // 5. تعديل جدول 'invoice_items' (إضافة حقل cost_price)
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->decimal('cost_price', 15, 2)->default(0)->after('unit_price'); // سعر التكلفة للبند
        });

        // 6. تعديل جدول 'invoices' (إضافة حقل estimated_profit)
        Schema::table('invoices', function (Blueprint $table) {
            $table->decimal('estimated_profit', 15, 2)->default(0)->after('total_amount'); // الربح التقديري الإجمالي للفاتورة
        });

        // 7. تعديل جدول 'cash_boxes' (الربط بـ payment_methods)
        Schema::table('cash_boxes', function (Blueprint $table) {
            // حذف المفتاح الخارجي القديم أولاً إذا كان موجوداً
            if (Schema::hasColumn('cash_boxes', 'cash_box_type_id')) {
                $table->dropForeign(['cash_box_type_id']);
                // حذف العمود القديم
                $table->dropColumn('cash_box_type_id');
            }
            // إضافة عمود المفتاح الخارجي الجديد لربطه بـ 'payment_methods'
            $table->foreignId('payment_method_id')->nullable()->constrained('payment_methods')->onDelete('set null')->after('balance');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 1. عكس تعديلات جدول 'cash_boxes'
        Schema::table('cash_boxes', function (Blueprint $table) {
            $table->dropForeign(['payment_method_id']);
            $table->dropColumn('payment_method_id');
            // إعادة العمود والمفتاح الخارجي القديمين (إذا لزم الأمر للعودة)
            $table->foreignId('cash_box_type_id')->nullable()->constrained('cash_box_types')->onDelete('set null')->after('balance');
        });

        // 2. عكس تعديلات جدول 'invoices'
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('estimated_profit');
        });

        // 3. عكس تعديلات جدول 'invoice_items'
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropColumn('cost_price');
        });

        // 4. عكس تعديلات جدول 'installment_payment_details'
        Schema::table('installment_payment_details', function (Blueprint $table) {
            $table->dropUnique(['payment_id', 'installment_id']);
            $table->dropForeign(['payment_id']);
            $table->dropColumn('payment_id');

            // إعادة العمود القديم (بدون مفتاح أجنبي لأنه تم حذف الجدول الأصلي)
            $table->unsignedBigInteger('installment_payment_id')->nullable()->after('id');
        });

        // 5. عكس تعديلات جدول 'payments'
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['payable_type', 'payable_id']);
            $table->dropForeign(['financial_transaction_id']);
            $table->dropColumn('financial_transaction_id');
            $table->dropForeign(['cash_box_id']);
            $table->dropColumn('cash_box_id');
            $table->dropColumn('payment_type');
            $table->dropColumn('payable_type');
            $table->dropColumn('payable_id');
            $table->dropColumn('realized_profit_amount'); // حذف الحقل الجديد
        });

        // 6. إعادة إنشاء الجداول التي تم حذفها (كقشور فارغة للـ rollback)
        // ملاحظة: هذا يعيد إنشاء الجداول فقط، بدون استعادة البيانات أو الهياكل الأصلية الكاملة.
        // لاستعادة الهياكل الكاملة والبيانات، ستحتاج إلى تشغيل الهجرات الأصلية لتلك الجداول.

        // Schema::create('profits', function (Blueprint $table) { /* ... */ });
        // Schema::create('revenues', function (Blueprint $table) { /* ... */ });
        // Schema::create('installment_payments', function (Blueprint $table) { /* ... */ });
        // Schema::create('payment_installment', function (Blueprint $table) { /* ... */ });
        // Schema::create('cash_box_types', function (Blueprint $table) { /* ... */ }); // إذا كنت تريد استعادة هذا الجدول
    }
};
