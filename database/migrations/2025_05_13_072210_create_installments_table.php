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
            $table->id();

            $table->foreignId('installment_plan_id')
                ->constrained('installment_plans')
                ->onDelete('cascade');

            $table->foreignId('user_id')
                ->constrained('users')
                ->onDelete('cascade'); // العميل

            $table->foreignId('created_by')
                ->constrained('users')
                ->onDelete('cascade'); // أنشئ بواسطة

            $table->foreignId('company_id')
                ->constrained('companies') // ✅ تم تصحيحه من Company إلى companies
                ->onDelete('cascade');     // الشركة

            $table->string('installment_number')->nullable();

            $table->date('due_date');
            $table->decimal('amount', 15, 2);
            $table->decimal('remaining', 15, 2)->default(0);
            $table->string('status'); // لم يتم الدفع، تم الدفع، متأخر، ملغي، وهكذا
            $table->timestamp('paid_at')->nullable();

            $table->timestamps();
            $table->softDeletes(); // ✅ دعم soft delete
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
