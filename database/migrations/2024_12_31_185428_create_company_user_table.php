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

        Schema::create('company_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null'); // المستخدم الذي أضاف العلاقة

            $table->enum('role', ['manager', 'employee', 'customer'])->default('customer')->nullable();
            $table->enum('status', ['active', 'pending', 'suspended'])->default('active')->nullable();

            // *** الحقول الخاصة بالشركة التي تم نقلها من جدول users أو إضافتها ***
            // هذه الحقول تمثل بيانات المستخدم/العميل كما تراها هذه الشركة
            $table->string('nickname_in_company')->nullable()->comment('اسم المستخدم/العميل في سياق هذه الشركة');
            $table->string('full_name_in_company')->nullable()->comment('الاسم الكامل للمستخدم/العميل كما تعرفه هذه الشركة');
            $table->string('position_in_company')->nullable()->comment('منصب المستخدم إذا كان موظفاً في هذه الشركة');
            $table->decimal('balance_in_company', 10, 2)->default(0)->comment('رصيد العميل في هذه الشركة');
            $table->string('customer_type_in_company')->default('retail')->comment('نوع العميل (تجزئة/جملة) في سياق هذه الشركة');

            // حقول لنسخ بيانات المستخدم الأساسية من جدول users للمزامنة
            // هذه الحقول سيتم تحديثها تلقائياً من جدول users عندما يقوم المستخدم بتعديلها
            $table->string('user_phone')->nullable()->comment('رقم الهاتف الأساسي للمستخدم (للمزامنة من جدول users)');
            $table->string('user_email')->nullable()->comment('البريد الإلكتروني الأساسي للمستخدم (للمزامنة من جدول users)');
            $table->string('user_username')->nullable()->comment('اسم المستخدم الأساسي (للمزامنة من جدول users)');


            $table->timestamps();

            // مفتاح فريد لضمان أن المستخدم لا يمكن أن يكون له علاقتين من نفس النوع (مثلاً عميل مرتين) بنفس الشركة.
            // أو يمكن أن يكون فقط على company_id و user_id إذا كان المستخدم لا يمكن أن يكون له أكثر من دور واحد في نفس الشركة
            $table->unique(['company_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_user');
    }
};
