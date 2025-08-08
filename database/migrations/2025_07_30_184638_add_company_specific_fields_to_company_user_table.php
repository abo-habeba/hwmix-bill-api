<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * تشغيل عمليات الترحيل.
     * هذا الترحيل يضيف الحقول الجديدة لجدول company_user.
     */
    public function up(): void
    {
        Schema::table('company_user', function (Blueprint $table) {
            // إضافة الحقول الخاصة بالشركة التي تم نقلها من جدول users أو إضافتها
            // هذه الحقول تمثل بيانات المستخدم/العميل كما تراها هذه الشركة
            $table->string('nickname_in_company')->nullable()->after('created_by')->comment('الاسم المستعار للمستخدم داخل هذه الشركة');
            $table->string('full_name_in_company')->nullable()->after('nickname_in_company')->comment('الاسم الكامل للمستخدم/العميل كما تعرفه هذه الشركة');
            $table->string('position_in_company')->nullable()->after('full_name_in_company')->comment('المسمى الوظيفي للمستخدم في هذه الشركة (للموظفين)');
            $table->decimal('balance_in_company', 15, 2)->default(0)->after('position_in_company')->comment('رصيد العميل في هذه الشركة');
            $table->string('customer_type_in_company')->default('retail')->after('balance_in_company')->comment('نوع العميل (تجزئة/جملة) في سياق هذه الشركة');
            // ملاحظة: حقل 'status' موجود بالفعل في جدول company_user، ويمكن استخدامه كـ 'status_in_company'

            // حقول لمزامنة البيانات الأساسية للمستخدم من جدول users
            // هذه الحقول سيتم تحديثها تلقائياً من جدول users عندما يقوم المستخدم بتعديلها
            $table->string('user_phone')->nullable()->after('customer_type_in_company');
            $table->string('user_email')->nullable()->after('user_phone');
            $table->string('user_username')->nullable()->after('user_email');

            // إضافة مفتاح فريد لضمان أن كل مستخدم يمكن أن يرتبط بكل شركة مرة واحدة فقط
            // تأكد أن هذا المفتاح لا يتعارض مع أي مفاتيح فريدة موجودة لديك
            // إذا كان لديك بالفعل مفتاح فريد على ['company_id', 'user_id']، لا تضفه مرة أخرى.
            // إذا كان لديك مفتاح فريد على ['company_id', 'user_id', 'role']، ففكر في هذا المفتاح الفريد الجديد.
            $table->unique(['company_id', 'user_id']);
        });
    }

    /**
     * التراجع عن عمليات الترحيل.
     * هذا الترحيل يقوم بإزالة الحقول المضافة.
     */
    public function down(): void
    {
        Schema::table('company_user', function (Blueprint $table) {
            // إزالة المفتاح الفريد أولاً إذا تم إضافته في up()
            $table->dropUnique(['company_id', 'user_id']);

            // إزالة الحقول المضافة
            $table->dropColumn([
                'nickname_in_company',
                'full_name_in_company',
                'position_in_company',
                'balance_in_company',
                'customer_type_in_company',
                'user_phone',
                'user_email',
                'user_username',
            ]);
        });
    }
};
