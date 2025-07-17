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
        Schema::table('installment_payments', function (Blueprint $table) {
            $table->foreignId('cash_box_id')
                ->nullable() // اجعله قابلاً للقيمة Null إذا كانت الدفعات لا ترتبط دائماً بصندوق نقدي
                ->constrained('cash_boxes') // افترض أن جدول صناديق النقدية اسمه 'cash_boxes'
                ->onDelete('set null') // إذا حُذف صندوق النقدية، اجعل القيمة Null
                ->after('amount_paid'); // ضع العمود بعد 'amount_paid' لتحسين التنظيم
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('installment_payments', function (Blueprint $table) {
            //
        });
    }
};
