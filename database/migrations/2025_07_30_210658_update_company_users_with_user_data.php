<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB; // تأكد من استيراد DB

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // تحديث الصفوف الموجودة في جدول company_users
        DB::table('company_users')
            ->get() // جلب جميع الصفوف لتحديثها
            ->each(function ($companyUser) {
                // ابحث عن المستخدم الأساسي المرتبط بهذا السجل
                $user = DB::table('users')->find($companyUser->user_id);

                if ($user) {
                    // تحديث الحقول الجديدة ببيانات المستخدم الأساسية
                    DB::table('company_users')
                        ->where('id', $companyUser->id)
                        ->update([
                            'nickname_in_company' => $user->nickname, // أو أي حقل آخر تريد استخدامه كاسم مستعار
                            'full_name_in_company' => $user->full_name ?? null, // افترض وجود full_name أو استخدم username
                            'position_in_company' => $user->position ?? null, // افترض وجود position
                            'user_phone' => $user->phone,
                            'user_email' => $user->email,
                            'user_username' => $user->username,
                            'user_username' => $user->username,
                            // 'balance_in_company' و 'customer_type_in_company' و 'status'
                            // هذه الحقول قد تحتاج إلى منطق خاص بها إذا لم تكن مرتبطة مباشرة بـ user
                            // إذا كانت قيمتها الافتراضية 0 أو 'default' أو 'active' كما في الكنترولر،
                            // يمكنك تعيينها هنا إذا لم تكن قد عُينت بالفعل.
                            // 'balance_in_company' => $companyUser->balance_in_company ?? 0,
                            // 'customer_type_in_company' => $companyUser->customer_type_in_company ?? 'default',
                        ]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // في دالة down، يمكنك عكس التغييرات إذا لزم الأمر،
        // ولكن عادةً لا يتم عكس تحديثات البيانات بهذه الطريقة.
        // يمكنك تركها فارغة أو إضافة منطق لإعادة تعيين القيم إلى NULL إذا كان ذلك مطلوبًا.
    }
};
