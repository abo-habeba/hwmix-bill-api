<?php

/**
 * -----------------------------------------------------------------------------
 * Permission Keys Registry — Arabic Labels
 * -----------------------------------------------------------------------------
 * هذا الملف هو المصدر الوحيد الرسمي لتعريف مفاتيح الصلاحيات (permission keys)
 * المستخدمة في الباك إند والفرونت إند، ويُرجى الرجوع إليه فقط للحصول على أسماء
 * الصلاحيات سواء في الكود أو عند إنشاء بيانات seeder أو التعامل معها من الواجهة.
 *
 * ✅ يُستخدم هذا الملف في:
 * - توليد seeders الخاصة بالصلاحيات.
 * - إنشاء واجهات المستخدم للوحة التحكم.
 * - التحقق من الصلاحيات في Controllers, Policies, Gates إلخ.
 * - الترجمة والتمثيل البصري لأسماء الصلاحيات.
 *
 * ✅ دالة المساعد `perm_key('entity.action')` تُستخدم للوصول إلى المفتاح الرسمي.
 * ➤ مثال: perm_key('users.update_any') → "users.update_any"
 *
 * ✅ يجب أن تحتوي كل صلاحية على:
 * - key   → الاسم الموحد المحفوظ في قاعدة البيانات (بالإنجليزية)
 * - label → التسمية الظاهرة في الواجهة (بالعربية)
 *
 * -----------------------------------------------------------------------------
 * شرح مفصل لأنواع الصلاحيات (actions) ونطاقها:
 * -----------------------------------------------------------------------------
 * - name: يشير إلى اسم المجموعة الكلية للصلاحيات ويعبر عن وظيفتها أو يصفها.
 * - page:
 * السماح بالوصول إلى الصفحة الرئيسية أو قائمة إدارة كيان معين (مثل 'صفحة المستخدمين'
 * أو 'صفحة الشركات'). لا تمنح صلاحيات عرض السجلات، بل فقط الوصول لواجهة الإدارة.
 *
 * - view_all:
 * عرض جميع السجلات من الكيان المعني **ضمن نطاق الشركة النشطة** للمستخدم.
 * لا يمنح صلاحيات تعديل أو حذف، ويرى السجلات بغض النظر عن مُنشئها.
 *
 * - view_children:
 * عرض السجلات التي قام المستخدم الحالي بإنشائها، أو التي أنشأها المستخدمون
 * الذين يتبعون له في الهيكل التنظيمي (التابعين له أو "الأبناء"). يُستخدم هذا
 * في الأنظمة الهرمية لتقييد الرؤية ضمن فروع معينة.
 *
 * - view_self:
 * عرض السجل الذي يخص المستخدم نفسه فقط، مثل حسابه الشخصي أو تفاصيل شركته
 * الخاصة به. يُستخدم هذا لتعديل البيانات الشخصية دون رؤية بيانات الآخرين.
 *
 * - create:
 * إنشاء سجل جديد في هذا الكيان **ضمن نطاق الشركة النشطة**، مثل إضافة مستخدم
 * جديد أو إنشاء شركة جديدة.
 *
 * - update_any:
 * تعديل أي سجل داخل الكيان **ضمن نطاق الشركة النشطة** للمستخدم، دون قيود على
 * من أنشأ السجل أو ملكيته.
 *
 * - update_children:
 * تعديل السجلات التي قام المستخدم الحالي بإنشائها، أو التي أنشأها المستخدمون
 * التابعون له في الهيكل التنظيمي (الأبناء).
 *
 * - update_self:
 * تعديل السجل المرتبط بالمستخدم مباشرة فقط (مثل تعديل ملفه الشخصي أو بيانات شركته
 * الخاصة به).
 *
 * - delete_any:
 * حذف أي سجل من الكيان **ضمن نطاق الشركة النشطة** للمستخدم، بغض النظر عن الملكية.
 *
 * - delete_children:
 * حذف السجلات التي قام المستخدم الحالي بإنشائها، أو التي أنشأها المستخدمون
 * التابعون له في الهيكل التنظيمي (الأبناء).
 *
 * - delete_self:
 * حذف السجل الخاص بالمستخدم نفسه فقط (على سبيل المثال، تعطيل حسابه الشخصي).
 *
 * ◾ الكيانات (entities): مثل users, companies, warehouses … إلخ.
 * ◾ كل كيان يحتوي على مجموعة من الصلاحيات حسب نوع التعامل معه.
 * -----------------------------------------------------------------------------
 */
return [
    // => ADMIN
    'admin' => [
        'name' => ['key' => 'admin', 'label' => 'صلاحيات المديرين'],
        'page' => ['key' => 'admin.page', 'label' => 'الصفحة الرئيسية'],
        'super' => ['key' => 'admin.super', 'label' => ' صلاحية المدير العام'],
        'company' => ['key' => 'admin.company', 'label' => 'صلاحية ادارة الشركة'],
    ],
    // => COMPANIES
    'companies' => [
        'name' => ['key' => 'companies', 'label' => 'صلاحيات إدارة الشركات'],
        'change_active_company' => ['key' => 'companies.change_active_company', 'label' => 'تغيير الشركة النشطة'],
        'page' => ['key' => 'companies.page', 'label' => 'صفحة الشركات'],

        'view_all' => ['key' => 'companies.view_all', 'label' => 'عرض كل الشركات'],
        'view_children' => ['key' => 'companies.view_children', 'label' => 'عرض الشركات التابعة'],
        'view_self' => ['key' => 'companies.view_self', 'label' => 'عرض الشركة الحالية'],
        // صلاحيات الإنشاء (Create)
        'create' => ['key' => 'companies.create', 'label' => 'إنشاء شركة'],
        // صلاحيات التعديل (Update)
        'update_any' => ['key' => 'companies.update_any', 'label' => 'تعديل أى شركة'],
        'update_children' => ['key' => 'companies.update_children', 'label' => 'تعديل الشركات التابعة'],
        'update_self' => ['key' => 'companies.update_self', 'label' => 'تعديل الشركة الحالية'],
        // صلاحيات الحذف (Delete)
        'delete_any' => ['key' => 'companies.delete_any', 'label' => 'حذف أى شركة'],
        'delete_children' => ['key' => 'companies.delete_children', 'label' => 'حذف الشركات التابعة'],
        'delete_self' => ['key' => 'companies.delete_self', 'label' => 'حذف الشركة الحالية'],
    ],
    // => USERS
    'users' => [
        'name' => ['key' => 'users', 'label' => 'صلاحيات إدارة المستخدمين'],
        'page' => ['key' => 'users.page', 'label' => 'صفحة المستخدمين'],
        // صلاحيات العرض (View)
        'view_all' => ['key' => 'users.view_all', 'label' => 'عرض كل المستخدمين'],
        'view_children' => ['key' => 'users.view_children', 'label' => 'عرض المستخدمين التابعين'],
        'view_self' => ['key' => 'users.view_self', 'label' => 'عرض الحساب الشخصى'],
        // صلاحيات الإنشاء (Create)
        'create' => ['key' => 'users.create', 'label' => 'إنشاء مستخدم'],
        // صلاحيات التعديل (Update)
        'update_any' => ['key' => 'users.update_any', 'label' => 'تعديل أى مستخدم'],
        'update_children' => ['key' => 'users.update_children', 'label' => 'تعديل التابعين'],
        'update_self' => ['key' => 'users.update_self', 'label' => 'تعديل حسابه'],
        // صلاحيات الحذف (Delete)
        'delete_any' => ['key' => 'users.delete_any', 'label' => 'حذف أى مستخدم'],
        'delete_children' => ['key' => 'users.delete_children', 'label' => 'حذف التابعين'],
        'delete_self' => ['key' => 'users.delete_self', 'label' => 'حذف حسابه'],
    ],
    // => PERSONAL ACCESS TOKENS
    'personal_access_tokens' => [
        'name' => ['key' => 'personal_access_tokens', 'label' => 'صلاحيات إدارة رموز الوصول الشخصية'],
        'page' => ['key' => 'personal_access_tokens.page', 'label' => 'صفحة رموز الوصول الشخصية'],
        // صلاحيات العرض (View)
        'view_all' => ['key' => 'personal_access_tokens.view_all', 'label' => 'عرض كل رموز الوصول'],
        'view_children' => ['key' => 'personal_access_tokens.view_children', 'label' => 'عرض رموز الوصول التي أنشأها المستخدمون التابعون'],
        'view_self' => ['key' => 'personal_access_tokens.view_self', 'label' => 'عرض رموز الوصول الخاصة بالمستخدم'],
        // صلاحيات الإنشاء (Create)
        'create' => ['key' => 'personal_access_tokens.create', 'label' => 'إنشاء رمز وصول'],
        // صلاحيات التعديل (Update)
        'update_any' => ['key' => 'personal_access_tokens.update_any', 'label' => 'تعديل أي رمز وصول'],
        'update_children' => ['key' => 'personal_access_tokens.update_children', 'label' => 'تعديل رموز الوصول التي أنشأها المستخدمون التابعون'],
        'update_self' => ['key' => 'personal_access_tokens.update_self', 'label' => 'تعديل رموز الوصول الخاصة بالمستخدم'],
        // صلاحيات الحذف (Delete)
        'delete_any' => ['key' => 'personal_access_tokens.delete_any', 'label' => 'حذف أي رمز وصول'],
        'delete_children' => ['key' => 'personal_access_tokens.delete_children', 'label' => 'حذف رموز الوصول التي أنشأها المستخدمون التابعون'],
        'delete_self' => ['key' => 'personal_access_tokens.delete_self', 'label' => 'حذف رموز الوصول الخاصة بالمستخدم'],
    ],
    // => TRANSLATIONS
    'translations' => [
        'name' => ['key' => 'translations', 'label' => 'صلاحيات إدارة الترجمات'],
        'page' => ['key' => 'translations.page', 'label' => 'الوصول إلى صفحة الترجمات'],
        // صلاحيات العرض (View)
        'view_all' => ['key' => 'translations.view_all', 'label' => 'عرض جميع الترجمات'],
        'view_children' => ['key' => 'translations.view_children', 'label' => 'عرض الترجمات التي أنشأها التابعون'],
        'view_self' => ['key' => 'translations.view_self', 'label' => 'عرض الترجمات الخاصة بالمستخدم'],
        // صلاحيات الإنشاء (Create)
        'create' => ['key' => 'translations.create', 'label' => 'إنشاء ترجمة جديدة'],
        // صلاحيات التعديل (Update)
        'update_any' => ['key' => 'translations.update_any', 'label' => 'تعديل أي ترجمة'],
        'update_children' => ['key' => 'translations.update_children', 'label' => 'تعديل الترجمات التي أنشأها التابعون'],
        'update_self' => ['key' => 'translations.update_self', 'label' => 'تعديل الترجمات الخاصة بالمستخدم'],
        // صلاحيات الحذف (Delete)
        'delete_any' => ['key' => 'translations.delete_any', 'label' => 'حذف أي ترجمة'],
        'delete_children' => ['key' => 'translations.delete_children', 'label' => 'حذف الترجمات التي أنشأها التابعون'],
        'delete_self' => ['key' => 'translations.delete_self', 'label' => 'حذف الترجمات الخاصة بالمستخدم'],
    ],
    // => TRANSACTIONS
    'transactions' => [
        'name' => ['key' => 'transactions', 'label' => 'صلاحيات إدارة المعاملات'],
        'page' => ['key' => 'transactions.page', 'label' => 'الوصول إلى صفحة المعاملات'],
        // صلاحيات العرض (View)
        'view_all' => ['key' => 'transactions.view_all', 'label' => 'عرض جميع المعاملات'],
        'view_children' => ['key' => 'transactions.view_children', 'label' => 'عرض المعاملات التي أنشأها التابعون'],
        'view_self' => ['key' => 'transactions.view_self', 'label' => 'عرض المعاملات الخاصة بالمستخدم'],
        // صلاحيات الإنشاء (Create)
        'create' => ['key' => 'transactions.create', 'label' => 'إنشاء معاملة جديدة'],
        // صلاحيات التعديل (Update)
        'update_any' => ['key' => 'transactions.update_any', 'label' => 'تعديل أي معاملة'],
        'update_children' => ['key' => 'transactions.update_children', 'label' => 'تعديل المعاملات التي أنشأها التابعون'],
        'update_self' => ['key' => 'transactions.update_self', 'label' => 'تعديل المعاملات الخاصة بالمستخدم'],
        // صلاحيات الحذف (Delete)
        'delete_any' => ['key' => 'transactions.delete_any', 'label' => 'حذف أي معاملة'],
        'delete_children' => ['key' => 'transactions.delete_children', 'label' => 'حذف المعاملات التي أنشأها التابعون'],
        'delete_self' => ['key' => 'transactions.delete_self', 'label' => 'حذف المعاملات الخاصة بالمستخدم'],
    ],
    // => ACTIVITY LOGS
    'activity_logs' => [
        'name' => ['key' => 'activity_logs', 'label' => 'صلاحيات إدارة سجلات الأنشطة'],
        'page' => ['key' => 'activity_logs.page', 'label' => 'الوصول إلى صفحة سجلات الأنشطة'],
        // صلاحيات العرض (View)
        'view_all' => ['key' => 'activity_logs.view_all', 'label' => 'عرض جميع سجلات الأنشطة'],
        'view_children' => ['key' => 'activity_logs.view_children', 'label' => 'عرض سجلات الأنشطة التي أنشأها التابعون'],
        'view_self' => ['key' => 'activity_logs.view_self', 'label' => 'عرض سجلات الأنشطة الخاصة بالمستخدم'],

        // صلاحيات الحذف (Delete)
        'delete_any' => ['key' => 'activity_logs.delete_any', 'label' => 'حذف أي سجل نشاط'],
        'delete_children' => ['key' => 'activity_logs.delete_children', 'label' => 'حذف سجلات الأنشطة التي أنشأها التابعون'],
        'delete_self' => ['key' => 'activity_logs.delete_self', 'label' => 'حذف سجلات الأنشطة الخاصة بالمستخدم'],
    ],
    // => CASH BOX TYPES
    'cash_box_types' => [
        'name' => ['key' => 'cash_box_types', 'label' => 'صلاحيات إدارة أنواع صناديق النقدية'],
        'page' => ['key' => 'cash_box_types.page', 'label' => 'الوصول إلى صفحة أنواع صناديق النقدية'],
        // صلاحيات العرض (View)
        'view_all' => ['key' => 'cash_box_types.view_all', 'label' => 'عرض جميع أنواع صناديق النقدية'],
        'view_children' => ['key' => 'cash_box_types.view_children', 'label' => 'عرض أنواع صناديق النقدية التي أنشأها التابعون'],
        'view_self' => ['key' => 'cash_box_types.view_self', 'label' => 'عرض أنواع صناديق النقدية الخاصة بالمستخدم'],
        // صلاحيات الإنشاء (Create)
        'create' => ['key' => 'cash_box_types.create', 'label' => 'إنشاء نوع صندوق نقدية جديد'],
        // صلاحيات التعديل (Update)
        'update_any' => ['key' => 'cash_box_types.update_any', 'label' => 'تعديل أي نوع صندوق نقدية'],
        'update_children' => ['key' => 'cash_box_types.update_children', 'label' => 'تعديل أنواع صناديق النقدية التي أنشأها التابعون'],
        'update_self' => ['key' => 'cash_box_types.update_self', 'label' => 'تعديل أنواع صناديق النقدية الخاصة بالمستخدم'],
        // صلاحيات الحذف (Delete)
        'delete_any' => ['key' => 'cash_box_types.delete_any', 'label' => 'حذف أي نوع صندوق نقدية'],
        'delete_children' => ['key' => 'cash_box_types.delete_children', 'label' => 'حذف أنواع صناديق النقدية التي أنشأها التابعون'],
        'delete_self' => ['key' => 'cash_box_types.delete_self', 'label' => 'حذف أنواع صناديق النقدية الخاصة بالمستخدم'],
    ],
    // => CASH BOXES
    'cash_boxes' => [
        'name' => ['key' => 'cash_boxes', 'label' => 'صلاحيات إدارة صناديق النقدية'],
        'page' => ['key' => 'cash_boxes.page', 'label' => 'الوصول إلى صفحة صناديق النقدية'],
        // صلاحيات العرض (View)
        'view_all' => ['key' => 'cash_boxes.view_all', 'label' => 'عرض جميع صناديق النقدية'],
        'view_children' => ['key' => 'cash_boxes.view_children', 'label' => 'عرض صناديق النقدية التي أنشأها التابعون'],
        'view_self' => ['key' => 'cash_boxes.view_self', 'label' => 'عرض صناديق النقدية الخاصة بالمستخدم'],
        // صلاحيات الإنشاء (Create)
        'create' => ['key' => 'cash_boxes.create', 'label' => 'إنشاء صندوق نقدية جديد'],
        // صلاحيات التعديل (Update)
        'update_any' => ['key' => 'cash_boxes.update_any', 'label' => 'تعديل أي صندوق نقدية'],
        'update_children' => ['key' => 'cash_boxes.update_children', 'label' => 'تعديل صناديق النقدية التي أنشأها التابعون'],
        'update_self' => ['key' => 'cash_boxes.update_self', 'label' => 'تعديل صناديق النقدية الخاصة بالمستخدم'],
        // صلاحيات الحذف (Delete)
        'delete_any' => ['key' => 'cash_boxes.delete_any', 'label' => 'حذف أي صندوق نقدية'],
        'delete_children' => ['key' => 'cash_boxes.delete_children', 'label' => 'حذف صناديق النقدية التي أنشأها التابعون'],
        'delete_self' => ['key' => 'cash_boxes.delete_self', 'label' => 'حذف صناديق النقدية الخاصة بالمستخدم'],
    ],
    // => IMAGES
    'images' => [
        'name' => ['key' => 'images', 'label' => 'صلاحيات إدارة الصور'],
        'page' => ['key' => 'images.page', 'label' => 'الوصول إلى صفحة الصور'],
        // صلاحيات العرض (View)
        'view_all' => ['key' => 'images.view_all', 'label' => 'عرض جميع الصور'],
        'view_children' => ['key' => 'images.view_children', 'label' => 'عرض الصور التي أنشأها التابعون'],
        'view_self' => ['key' => 'images.view_self', 'label' => 'عرض الصور الخاصة بالمستخدم'],
        // صلاحيات الإنشاء (Create)
        'create' => ['key' => 'images.create', 'label' => 'إضافة صورة جديدة'],
        // صلاحيات التعديل (Update)
        'update_any' => ['key' => 'images.update_any', 'label' => 'تعديل أي صورة'],
        'update_children' => ['key' => 'images.update_children', 'label' => 'تعديل الصور التي أنشأها التابعون'],
        'update_self' => ['key' => 'images.update_self', 'label' => 'تعديل الصور الخاصة بالمستخدم'],
        // صلاحيات الحذف (Delete)
        'delete_any' => ['key' => 'images.delete_any', 'label' => 'حذف أي صورة'],
        'delete_children' => ['key' => 'images.delete_children', 'label' => 'حذف الصور التي أنشأها التابعون'],
        'delete_self' => ['key' => 'images.delete_self', 'label' => 'حذف الصور الخاصة بالمستخدم'],
    ],
    // => WAREHOUSES
    'warehouses' => [
        'name' => ['key' => 'warehouses', 'label' => 'صلاحيات إدارة المستودعات'],
        'page' => ['key' => 'warehouses.page', 'label' => 'الوصول إلى صفحة المستودعات'],
        // صلاحيات العرض (View)
        'view_all' => ['key' => 'warehouses.view_all', 'label' => 'عرض جميع المستودعات'],
        'view_children' => ['key' => 'warehouses.view_children', 'label' => 'عرض المستودعات التي أنشأها التابعون'],
        'view_self' => ['key' => 'warehouses.view_self', 'label' => 'عرض المستودعات الخاصة بالمستخدم'],
        // صلاحيات الإنشاء (Create)
        'create' => ['key' => 'warehouses.create', 'label' => 'إنشاء مستودع جديد'],
        // صلاحيات التعديل (Update)
        'update_any' => ['key' => 'warehouses.update_any', 'label' => 'تعديل أي مستودع'],
        'update_children' => ['key' => 'warehouses.update_children', 'label' => 'تعديل المستودعات التي أنشأها التابعون'],
        'update_self' => ['key' => 'warehouses.update_self', 'label' => 'تعديل المستودعات الخاصة بالمستخدم'],
        // صلاحيات الحذف (Delete)
        'delete_any' => ['key' => 'warehouses.delete_any', 'label' => 'حذف أي مستودع'],
        'delete_children' => ['key' => 'warehouses.delete_children', 'label' => 'حذف المستودعات التي أنشأها التابعون'],
        'delete_self' => ['key' => 'warehouses.delete_self', 'label' => 'حذف المستودعات الخاصة بالمستخدم'],
    ],
    // => CATEGORIES
    'categories' => [
        'name' => ['key' => 'categories', 'label' => 'صلاحيات إدارة الفئات'],
        'page' => ['key' => 'categories.page', 'label' => 'الوصول إلى صفحة الفئات'],
        // صلاحيات العرض (View)
        'view_all' => ['key' => 'categories.view_all', 'label' => 'عرض جميع الفئات'],
        'view_children' => ['key' => 'categories.view_children', 'label' => 'عرض الفئات التي أنشأها التابعون'],
        'view_self' => ['key' => 'categories.view_self', 'label' => 'عرض الفئات الخاصة بالمستخدم'],
        // صلاحيات الإنشاء (Create)
        'create' => ['key' => 'categories.create', 'label' => 'إنشاء فئة جديدة'],
        // صلاحيات التعديل (Update)
        'update_any' => ['key' => 'categories.update_any', 'label' => 'تعديل أي فئة'],
        'update_children' => ['key' => 'categories.update_children', 'label' => 'تعديل الفئات التي أنشأها التابعون'],
        'update_self' => ['key' => 'categories.update_self', 'label' => 'تعديل الفئات الخاصة بالمستخدم'],
        // صلاحيات الحذف (Delete)
        'delete_any' => ['key' => 'categories.delete_any', 'label' => 'حذف أي فئة'],
        'delete_children' => ['key' => 'categories.delete_children', 'label' => 'حذف الفئات التي أنشأها التابعون'],
        'delete_self' => ['key' => 'categories.delete_self', 'label' => 'حذف الفئات الخاصة بالمستخدم'],
    ],
    // => BRANDS
    'brands' => [
        'name' => ['key' => 'brands', 'label' => 'صلاحيات إدارة العلامات التجارية'],
        'page' => ['key' => 'brands.page', 'label' => 'الوصول إلى صفحة العلامات التجارية'],
        // صلاحيات العرض (View)
        'view_all' => ['key' => 'brands.view_all', 'label' => 'عرض جميع العلامات التجارية'],
        'view_children' => ['key' => 'brands.view_children', 'label' => 'عرض العلامات التجارية التي أنشأها التابعون'],
        'view_self' => ['key' => 'brands.view_self', 'label' => 'عرض العلامات التجارية الخاصة بالمستخدم'],
        // صلاحيات الإنشاء (Create)
        'create' => ['key' => 'brands.create', 'label' => 'إنشاء علامة تجارية جديدة'],
        // صلاحيات التعديل (Update)
        'update_any' => ['key' => 'brands.update_any', 'label' => 'تعديل أي علامة تجارية'],
        'update_children' => ['key' => 'brands.update_children', 'label' => 'تعديل العلامات التجارية التي أنشأها التابعون'],
        'update_self' => ['key' => 'brands.update_self', 'label' => 'تعديل العلامات التجارية الخاصة بالمستخدم'],
        // صلاحيات الحذف (Delete)
        'delete_any' => ['key' => 'brands.delete_any', 'label' => 'حذف أي علامة تجارية'],
        'delete_children' => ['key' => 'brands.delete_children', 'label' => 'حذف العلامات التجارية التي أنشأها التابعون'],
        'delete_self' => ['key' => 'brands.delete_self', 'label' => 'حذف العلامات التجارية الخاصة بالمستخدم'],
    ],
    // => ATTRIBUTES
    'attributes' => [
        'name' => ['key' => 'attributes', 'label' => 'صلاحيات إدارة السمات'],
        'page' => ['key' => 'attributes.page', 'label' => 'الوصول إلى صفحة السمات'],
        // صلاحيات العرض (View)
        'view_all' => ['key' => 'attributes.view_all', 'label' => 'عرض جميع السمات'],
        'view_children' => ['key' => 'attributes.view_children', 'label' => 'عرض السمات التي أنشأها التابعون'],
        'view_self' => ['key' => 'attributes.view_self', 'label' => 'عرض السمات الخاصة بالمستخدم'],
        // صلاحيات الإنشاء (Create)
        'create' => ['key' => 'attributes.create', 'label' => 'إنشاء سمة جديدة'],
        // صلاحيات التعديل (Update)
        'update_any' => ['key' => 'attributes.update_any', 'label' => 'تعديل أي سمة'],
        'update_children' => ['key' => 'attributes.update_children', 'label' => 'تعديل السمات التي أنشأها التابعون'],
        'update_self' => ['key' => 'attributes.update_self', 'label' => 'تعديل السمات الخاصة بالمستخدم'],
        // صلاحيات الحذف (Delete)
        'delete_any' => ['key' => 'attributes.delete_any', 'label' => 'حذف أي سمة'],
        'delete_children' => ['key' => 'attributes.delete_children', 'label' => 'حذف السمات التي أنشأها التابعون'],
        'delete_self' => ['key' => 'attributes.delete_self', 'label' => 'حذف السمات الخاصة بالمستخدم'],
    ],
    // => ATTRIBUTE VALUES
    'attribute_values' => [
        'name' => ['key' => 'attribute_values', 'label' => 'صلاحيات إدارة قيم السمات'],
        'page' => ['key' => 'attribute_values.page', 'label' => 'الوصول إلى صفحة قيم السمات'],
        // صلاحيات العرض (View)
        'view_all' => ['key' => 'attribute_values.view_all', 'label' => 'عرض جميع قيم السمات'],
        'view_children' => ['key' => 'attribute_values.view_children', 'label' => 'عرض قيم السمات التي أنشأها التابعون'],
        'view_self' => ['key' => 'attribute_values.view_self', 'label' => 'عرض قيم السمات الخاصة بالمستخدم'],
        // صلاحيات الإنشاء (Create)
        'create' => ['key' => 'attribute_values.create', 'label' => 'إنشاء قيمة سمة جديدة'],
        // صلاحيات التعديل (Update)
        'update_any' => ['key' => 'attribute_values.update_any', 'label' => 'تعديل أي قيمة سمة'],
        'update_children' => ['key' => 'attribute_values.update_children', 'label' => 'تعديل قيم السمات التي أنشأها التابعون'],
        'update_self' => ['key' => 'attribute_values.update_self', 'label' => 'تعديل قيم السمات الخاصة بالمستخدم'],
        // صلاحيات الحذف (Delete)
        'delete_any' => ['key' => 'attribute_values.delete_any', 'label' => 'حذف أي قيمة سمة'],
        'delete_children' => ['key' => 'attribute_values.delete_children', 'label' => 'حذف قيم السمات التي أنشأها التابعون'],
        'delete_self' => ['key' => 'attribute_values.delete_self', 'label' => 'حذف قيم السمات الخاصة بالمستخدم'],
    ],
    // => PRODUCTS
    'products' => [
        'name' => ['key' => 'products', 'label' => 'صلاحيات إدارة المنتجات'],
        'page' => ['key' => 'products.page', 'label' => 'الوصول إلى صفحة المنتجات'],
        // صلاحيات العرض (View)
        'view_all' => ['key' => 'products.view_all', 'label' => 'عرض جميع المنتجات'],
        'view_children' => ['key' => 'products.view_children', 'label' => 'عرض المنتجات التي أنشأها التابعون'],
        'view_self' => ['key' => 'products.view_self', 'label' => 'عرض المنتجات الخاصة بالمستخدم'],
        // صلاحيات الإنشاء (Create)
        'create' => ['key' => 'products.create', 'label' => 'إنشاء منتج جديد'],
        // صلاحيات التعديل (Update)
        'update_any' => ['key' => 'products.update_any', 'label' => 'تعديل أي منتج'],
        'update_children' => ['key' => 'products.update_children', 'label' => 'تعديل المنتجات التي أنشأها التابعون'],
        'update_self' => ['key' => 'products.update_self', 'label' => 'تعديل المنتجات الخاصة بالمستخدم'],
        // صلاحيات الحذف (Delete)
        'delete_any' => ['key' => 'products.delete_any', 'label' => 'حذف أي منتج'],
        'delete_children' => ['key' => 'products.delete_children', 'label' => 'حذف المنتجات التي أنشأها التابعون'],
        'delete_self' => ['key' => 'products.delete_self', 'label' => 'حذف المنتجات الخاصة بالمستخدم'],
    ],
    // => PRODUCT VARIANTS
    'product_variants' => [
        'name' => ['key' => 'product_variants', 'label' => 'صلاحيات إدارة متغيرات المنتجات'],
        'page' => ['key' => 'product_variants.page', 'label' => 'الوصول إلى صفحة متغيرات المنتجات'],
        // صلاحيات العرض (View)
        'view_all' => ['key' => 'product_variants.view_all', 'label' => 'عرض جميع متغيرات المنتجات'],
        'view_children' => ['key' => 'product_variants.view_children', 'label' => 'عرض متغيرات المنتجات التي أنشأها التابعون'],
        'view_self' => ['key' => 'product_variants.view_self', 'label' => 'عرض متغيرات المنتجات الخاصة بالمستخدم'],
        // صلاحيات الإنشاء (Create)
        'create' => ['key' => 'product_variants.create', 'label' => 'إنشاء متغير منتج جديد'],
        // صلاحيات التعديل (Update)
        'update_any' => ['key' => 'product_variants.update_any', 'label' => 'تعديل أي متغير منتج'],
        'update_children' => ['key' => 'product_variants.update_children', 'label' => 'تعديل متغيرات المنتجات التي أنشأها التابعون'],
        'update_self' => ['key' => 'product_variants.update_self', 'label' => 'تعديل متغيرات المنتجات الخاصة بالمستخدم'],
        // صلاحيات الحذف (Delete)
        'delete_any' => ['key' => 'product_variants.delete_any', 'label' => 'حذف أي متغير منتج'],
        'delete_children' => ['key' => 'product_variants.delete_children', 'label' => 'حذف متغيرات المنتجات التي أنشأها التابعون'],
        'delete_self' => ['key' => 'product_variants.delete_self', 'label' => 'حذف متغيرات المنتجات الخاصة بالمستخدم'],
    ],
    // => PRODUCT VARIANT ATTRIBUTES
    'product_variant_attributes' => [
        'name' => ['key' => 'product_variant_attributes', 'label' => 'صلاحيات إدارة سمات متغيرات المنتجات'],
        'page' => ['key' => 'product_variant_attributes.page', 'label' => 'الوصول إلى صفحة سمات متغيرات المنتجات'],
        // صلاحيات العرض (View)
        'view_all' => ['key' => 'product_variant_attributes.view_all', 'label' => 'عرض جميع سمات متغيرات المنتجات'],
        'view_children' => ['key' => 'product_variant_attributes.view_children', 'label' => 'عرض سمات متغيرات المنتجات التي أنشأها التابعون'],
        'view_self' => ['key' => 'product_variant_attributes.view_self', 'label' => 'عرض سمات متغيرات المنتجات الخاصة بالمستخدم'],
        // صلاحيات الإنشاء (Create)
        'create' => ['key' => 'product_variant_attributes.create', 'label' => 'إنشاء سمة متغير منتج جديدة'],
        // صلاحيات التعديل (Update)
        'update_any' => ['key' => 'product_variant_attributes.update_any', 'label' => 'تعديل أي سمة متغير منتج'],
        'update_children' => ['key' => 'product_variant_attributes.update_children', 'label' => 'تعديل سمات متغيرات المنتجات التي أنشأها التابعون'],
        'update_self' => ['key' => 'product_variant_attributes.update_self', 'label' => 'تعديل سمات متغيرات المنتجات الخاصة بالمستخدم'],
        // صلاحيات الحذف (Delete)
        'delete_any' => ['key' => 'product_variant_attributes.delete_any', 'label' => 'حذف أي سمة متغير منتج'],
        'delete_children' => ['key' => 'product_variant_attributes.delete_children', 'label' => 'حذف سمات متغيرات المنتجات التي أنشأها التابعون'],
        'delete_self' => ['key' => 'product_variant_attributes.delete_self', 'label' => 'حذف سمات متغيرات المنتجات الخاصة بالمستخدم'],
    ],
    // => STOCKS
    'stocks' => [
        'name' => ['key' => 'stocks', 'label' => 'صلاحيات إدارة المخزون'],
        'page' => ['key' => 'stocks.page', 'label' => 'الوصول إلى صفحة المخزون'],
        // صلاحيات العرض (View)
        'view_all' => ['key' => 'stocks.view_all', 'label' => 'عرض جميع سجلات المخزون'],
        'view_children' => ['key' => 'stocks.view_children', 'label' => 'عرض سجلات المخزون التي أنشأها التابعون'],
        'view_self' => ['key' => 'stocks.view_self', 'label' => 'عرض سجلات المخزون الخاصة بالمستخدم'],
        // صلاحيات الإنشاء (Create)
        'create' => ['key' => 'stocks.create', 'label' => 'إنشاء سجل مخزون جديد'],
        // صلاحيات التعديل (Update)
        'update_any' => ['key' => 'stocks.update_any', 'label' => 'تعديل أي سجل مخزون'],
        'update_children' => ['key' => 'stocks.update_children', 'label' => 'تعديل سجلات المخزون التي أنشأها التابعون'],
        'update_self' => ['key' => 'stocks.update_self', 'label' => 'تعديل سجلات المخزون الخاصة بالمستخدم'],
        // صلاحيات الحذف (Delete)
        'delete_any' => ['key' => 'stocks.delete_any', 'label' => 'حذف أي سجل مخزون'],
        'delete_children' => ['key' => 'stocks.delete_children', 'label' => 'حذف سجلات المخزون التي أنشأها التابعون'],
        'delete_self' => ['key' => 'stocks.delete_self', 'label' => 'حذف سجلات المخزون الخاصة بالمستخدم'],
    ],
    // => INVOICES
    'invoices' => [
        'name' => ['key' => 'invoices', 'label' => 'صلاحيات إدارة الفواتير'],
        'page' => ['key' => 'invoices.page', 'label' => 'الوصول إلى صفحة الفواتير'],
        // صلاحيات العرض (View)
        'view_all' => ['key' => 'invoices.view_all', 'label' => 'عرض جميع الفواتير'],
        'view_children' => ['key' => 'invoices.view_children', 'label' => 'عرض الفواتير التي أنشأها التابعون'],
        'view_self' => ['key' => 'invoices.view_self', 'label' => 'عرض الفواتير الخاصة بالمستخدم'],
        // صلاحيات الإنشاء (Create)
        'create' => ['key' => 'invoices.create', 'label' => 'إنشاء فاتورة جديدة'],
        // صلاحيات التعديل (Update)
        'update_any' => ['key' => 'invoices.update_any', 'label' => 'تعديل أي فاتورة'],
        'update_children' => ['key' => 'invoices.update_children', 'label' => 'تعديل الفواتير التي أنشأها التابعون'],
        'update_self' => ['key' => 'invoices.update_self', 'label' => 'تعديل الفواتير الخاصة بالمستخدم'],
        // صلاحيات الحذف (Delete)
        'delete_any' => ['key' => 'invoices.delete_any', 'label' => 'حذف أي فاتورة'],
        'delete_children' => ['key' => 'invoices.delete_children', 'label' => 'حذف الفواتير التي أنشأها التابعون'],
        'delete_self' => ['key' => 'invoices.delete_self', 'label' => 'حذف الفواتير الخاصة بالمستخدم'],
    ],
    // => INSTALLMENT PLANS
    'installment_plans' => [
        'name' => ['key' => 'installment_plans', 'label' => 'صلاحيات إدارة خطط الأقساط'],
        'page' => ['key' => 'installment_plans.page', 'label' => 'الوصول إلى صفحة خطط الأقساط'],
        // صلاحيات العرض (View)
        'view_all' => ['key' => 'installment_plans.view_all', 'label' => 'عرض جميع خطط الأقساط'],
        'view_children' => ['key' => 'installment_plans.view_children', 'label' => 'عرض خطط الأقساط التي أنشأها التابعون'],
        'view_self' => ['key' => 'installment_plans.view_self', 'label' => 'عرض خطط الأقساط الخاصة بالمستخدم'],
        // صلاحيات الإنشاء (Create)
        'create' => ['key' => 'installment_plans.create', 'label' => 'إنشاء خطة أقساط جديدة'],
        // صلاحيات التعديل (Update)
        'update_any' => ['key' => 'installment_plans.update_any', 'label' => 'تعديل أي خطة أقساط'],
        'update_children' => ['key' => 'installment_plans.update_children', 'label' => 'تعديل خطط الأقساط التي أنشأها التابعون'],
        'update_self' => ['key' => 'installment_plans.update_self', 'label' => 'تعديل خطط الأقساط الخاصة بالمستخدم'],
        // صلاحيات الحذف (Delete)
        'delete_any' => ['key' => 'installment_plans.delete_any', 'label' => 'حذف أي خطة أقساط'],
        'delete_children' => ['key' => 'installment_plans.delete_children', 'label' => 'حذف خطط الأقساط التي أنشأها التابعون'],
        'delete_self' => ['key' => 'installment_plans.delete_self', 'label' => 'حذف خطط الأقساط الخاصة بالمستخدم'],
    ],
    // => INSTALLMENTS
    'installments' => [
        'name' => ['key' => 'installments', 'label' => 'صلاحيات إدارة الأقساط'],
        'page' => ['key' => 'installments.page', 'label' => 'الوصول إلى صفحة الأقساط'],
        // صلاحيات العرض (View)
        'view_all' => ['key' => 'installments.view_all', 'label' => 'عرض جميع الأقساط'],
        'view_children' => ['key' => 'installments.view_children', 'label' => 'عرض الأقساط التي أنشأها التابعون'],
        'view_self' => ['key' => 'installments.view_self', 'label' => 'عرض الأقساط الخاصة بالمستخدم'],
        // صلاحيات الإنشاء (Create)
        'create' => ['key' => 'installments.create', 'label' => 'إنشاء قسط جديد'],
        // صلاحيات التعديل (Update)
        'update_any' => ['key' => 'installments.update_any', 'label' => 'تعديل أي قسط'],
        'update_children' => ['key' => 'installments.update_children', 'label' => 'تعديل الأقساط التي أنشأها التابعون'],
        'update_self' => ['key' => 'installments.update_self', 'label' => 'تعديل الأقساط الخاصة بالمستخدم'],
        // صلاحيات الحذف (Delete)
        'delete_any' => ['key' => 'installments.delete_any', 'label' => 'حذف أي قسط'],
        'delete_children' => ['key' => 'installments.delete_children', 'label' => 'حذف الأقساط التي أنشأها التابعون'],
        'delete_self' => ['key' => 'installments.delete_self', 'label' => 'حذف الأقساط الخاصة بالمستخدم'],
    ],
    // => INSTALLMENT PAYMENTS
    'installment_payments' => [
        'name' => ['key' => 'installment_payments', 'label' => 'صلاحيات إدارة مدفوعات الأقساط'],
        'page' => ['key' => 'installment_payments.page', 'label' => 'الوصول إلى صفحة مدفوعات الأقساط'],
        // صلاحيات العرض (View)
        'view_all' => ['key' => 'installment_payments.view_all', 'label' => 'عرض جميع مدفوعات الأقساط'],
        'view_children' => ['key' => 'installment_payments.view_children', 'label' => 'عرض مدفوعات الأقساط التي أنشأها التابعون'],
        'view_self' => ['key' => 'installment_payments.view_self', 'label' => 'عرض مدفوعات الأقساط الخاصة بالمستخدم'],
        // صلاحيات الإنشاء (Create)
        'create' => ['key' => 'installment_payments.create', 'label' => 'إنشاء دفعة قسط جديدة'],
        // صلاحيات التعديل (Update)
        'update_any' => ['key' => 'installment_payments.update_any', 'label' => 'تعديل أي دفعة قسط'],
        'update_children' => ['key' => 'installment_payments.update_children', 'label' => 'تعديل مدفوعات الأقساط التي أنشأها التابعون'],
        'update_self' => ['key' => 'installment_payments.update_self', 'label' => 'تعديل مدفوعات الأقساط الخاصة بالمستخدم'],
        // صلاحيات الحذف (Delete)
        'delete_any' => ['key' => 'installment_payments.delete_any', 'label' => 'حذف أي دفعة قسط'],
        'delete_children' => ['key' => 'installment_payments.delete_children', 'label' => 'حذف مدفوعات الأقساط التي أنشأها التابعون'],
        'delete_self' => ['key' => 'installment_payments.delete_self', 'label' => 'حذف مدفوعات الأقساط الخاصة بالمستخدم'],
    ],
    // => INVOICE ITEMS
    'invoice_items' => [
        'name' => ['key' => 'invoice_items', 'label' => 'صلاحيات إدارة عناصر الفاتورة'],
        'page' => ['key' => 'invoice_items.page', 'label' => 'الوصول إلى صفحة عناصر الفاتورة'],
        // صلاحيات العرض (View)
        'view_all' => ['key' => 'invoice_items.view_all', 'label' => 'عرض جميع عناصر الفاتورة'],
        'view_children' => ['key' => 'invoice_items.view_children', 'label' => 'عرض عناصر الفاتورة التي أنشأها التابعون'],
        'view_self' => ['key' => 'invoice_items.view_self', 'label' => 'عرض عناصر الفاتورة الخاصة بالمستخدم'],
        // صلاحيات الإنشاء (Create)
        'create' => ['key' => 'invoice_items.create', 'label' => 'إنشاء عنصر فاتورة جديد'],
        // صلاحيات التعديل (Update)
        'update_any' => ['key' => 'invoice_items.update_any', 'label' => 'تعديل أي عنصر فاتورة'],
        'update_children' => ['key' => 'invoice_items.update_children', 'label' => 'تعديل عناصر الفاتورة التي أنشأها التابعون'],
        'update_self' => ['key' => 'invoice_items.update_self', 'label' => 'تعديل عناصر الفاتورة الخاصة بالمستخدم'],
        // صلاحيات الحذف (Delete)
        'delete_any' => ['key' => 'invoice_items.delete_any', 'label' => 'حذف أي عنصر فاتورة'],
        'delete_children' => ['key' => 'invoice_items.delete_children', 'label' => 'حذف عناصر الفاتورة التي أنشأها التابعون'],
        'delete_self' => ['key' => 'invoice_items.delete_self', 'label' => 'حذف عناصر الفاتورة الخاصة بالمستخدم'],
    ],
    // => PAYMENTS
    'payments' => [
        'name' => ['key' => 'payments', 'label' => 'صلاحيات إدارة المدفوعات'],
        'page' => ['key' => 'payments.page', 'label' => 'الوصول إلى صفحة المدفوعات'],
        // صلاحيات العرض (View)
        'view_all' => ['key' => 'payments.view_all', 'label' => 'عرض جميع المدفوعات'],
        'view_children' => ['key' => 'payments.view_children', 'label' => 'عرض المدفوعات التي أنشأها التابعون'],
        'view_self' => ['key' => 'payments.view_self', 'label' => 'عرض المدفوعات الخاصة بالمستخدم'],
        // صلاحيات الإنشاء (Create)
        'create' => ['key' => 'payments.create', 'label' => 'إنشاء دفعة جديدة'],
        // صلاحيات التعديل (Update)
        'update_any' => ['key' => 'payments.update_any', 'label' => 'تعديل أي دفعة'],
        'update_children' => ['key' => 'payments.update_children', 'label' => 'تعديل المدفوعات التي أنشأها التابعون'],
        'update_self' => ['key' => 'payments.update_self', 'label' => 'تعديل المدفوعات الخاصة بالمستخدم'],
        // صلاحيات الحذف (Delete)
        'delete_any' => ['key' => 'payments.delete_any', 'label' => 'حذف أي دفعة'],
        'delete_children' => ['key' => 'payments.delete_children', 'label' => 'حذف المدفوعات التي أنشأها التابعون'],
        'delete_self' => ['key' => 'payments.delete_self', 'label' => 'حذف المدفوعات الخاصة بالمستخدم'],
    ],
    // => PAYMENT METHODS
    'payment_methods' => [
        'name' => ['key' => 'payment_methods', 'label' => 'صلاحيات إدارة طرق الدفع'],
        'page' => ['key' => 'payment_methods.page', 'label' => 'الوصول إلى صفحة طرق الدفع'],
        // صلاحيات العرض (View)
        'view_all' => ['key' => 'payment_methods.view_all', 'label' => 'عرض جميع طرق الدفع'],
        'view_children' => ['key' => 'payment_methods.view_children', 'label' => 'عرض طرق الدفع التي أنشأها التابعون'],
        'view_self' => ['key' => 'payment_methods.view_self', 'label' => 'عرض طرق الدفع الخاصة بالمستخدم'],
        // صلاحيات الإنشاء (Create)
        'create' => ['key' => 'payment_methods.create', 'label' => 'إنشاء طريقة دفع جديدة'],
        // صلاحيات التعديل (Update)
        'update_any' => ['key' => 'payment_methods.update_any', 'label' => 'تعديل أي طريقة دفع'],
        'update_children' => ['key' => 'payment_methods.update_children', 'label' => 'تعديل طرق الدفع التي أنشأها التابعون'],
        'update_self' => ['key' => 'payment_methods.update_self', 'label' => 'تعديل طرق الدفع الخاصة بالمستخدم'],
        // صلاحيات الحذف (Delete)
        'delete_any' => ['key' => 'payment_methods.delete_any', 'label' => 'حذف أي طريقة دفع'],
        'delete_children' => ['key' => 'payment_methods.delete_children', 'label' => 'حذف طرق الدفع التي أنشأها التابعون'],
        'delete_self' => ['key' => 'payment_methods.delete_self', 'label' => 'حذف طرق الدفع الخاصة بالمستخدم'],
    ],
    // => REVENUES
    'revenues' => [
        'name' => ['key' => 'revenues', 'label' => 'صلاحيات إدارة الإيرادات'],
        'page' => ['key' => 'revenues.page', 'label' => 'الوصول إلى صفحة الإيرادات'],
        // صلاحيات العرض (View)
        'view_all' => ['key' => 'revenues.view_all', 'label' => 'عرض جميع الإيرادات'],
        'view_children' => ['key' => 'revenues.view_children', 'label' => 'عرض الإيرادات التي أنشأها التابعون'],
        'view_self' => ['key' => 'revenues.view_self', 'label' => 'عرض الإيرادات الخاصة بالمستخدم'],
        // صلاحيات الإنشاء (Create)
        'create' => ['key' => 'revenues.create', 'label' => 'إنشاء سجل إيراد جديد'],
        // صلاحيات التعديل (Update)
        'update_any' => ['key' => 'revenues.update_any', 'label' => 'تعديل أي إيراد'],
        'update_children' => ['key' => 'revenues.update_children', 'label' => 'تعديل الإيرادات التي أنشأها التابعون'],
        'update_self' => ['key' => 'revenues.update_self', 'label' => 'تعديل الإيرادات الخاصة بالمستخدم'],
        // صلاحيات الحذف (Delete)
        'delete_any' => ['key' => 'revenues.delete_any', 'label' => 'حذف أي إيراد'],
        'delete_children' => ['key' => 'revenues.delete_children', 'label' => 'حذف الإيرادات التي أنشأها التابعون'],
        'delete_self' => ['key' => 'revenues.delete_self', 'label' => 'حذف الإيرادات الخاصة بالمستخدم'],
    ],
    // => PROFITS
    'profits' => [
        'name' => ['key' => 'profits', 'label' => 'صلاحيات إدارة الأرباح'],
        'page' => ['key' => 'profits.page', 'label' => 'الوصول إلى صفحة الأرباح'],
        // صلاحيات العرض (View)
        'view_all' => ['key' => 'profits.view_all', 'label' => 'عرض جميع الأرباح'],
        'view_children' => ['key' => 'profits.view_children', 'label' => 'عرض الأرباح التي أنشأها التابعون'],
        'view_self' => ['key' => 'profits.view_self', 'label' => 'عرض الأرباح الخاصة بالمستخدم'],
        // صلاحيات الإنشاء (Create)
        'create' => ['key' => 'profits.create', 'label' => 'إنشاء سجل ربح جديد'],
        // صلاحيات التعديل (Update)
        'update_any' => ['key' => 'profits.update_any', 'label' => 'تعديل أي ربح'],
        'update_children' => ['key' => 'profits.update_children', 'label' => 'تعديل الأرباح التي أنشأها التابعون'],
        'update_self' => ['key' => 'profits.update_self', 'label' => 'تعديل الأرباح الخاصة بالمستخدم'],
        // صلاحيات الحذف (Delete)
        'delete_any' => ['key' => 'profits.delete_any', 'label' => 'حذف أي ربح'],
        'delete_children' => ['key' => 'profits.delete_children', 'label' => 'حذف الأرباح التي أنشأها التابعون'],
        'delete_self' => ['key' => 'profits.delete_self', 'label' => 'حذف الأرباح الخاصة بالمستخدم'],
    ],
    // => SERVICES
    'services' => [
        'name' => ['key' => 'services', 'label' => 'صلاحيات إدارة الخدمات'],
        'page' => ['key' => 'services.page', 'label' => 'الوصول إلى صفحة الخدمات'],
        // صلاحيات العرض (View)
        'view_all' => ['key' => 'services.view_all', 'label' => 'عرض جميع الخدمات'],
        'view_children' => ['key' => 'services.view_children', 'label' => 'عرض الخدمات التي أنشأها التابعون'],
        'view_self' => ['key' => 'services.view_self', 'label' => 'عرض الخدمات الخاصة بالمستخدم'],
        // صلاحيات الإنشاء (Create)
        'create' => ['key' => 'services.create', 'label' => 'إنشاء خدمة جديدة'],
        // صلاحيات التعديل (Update)
        'update_any' => ['key' => 'services.update_any', 'label' => 'تعديل أي خدمة'],
        'update_children' => ['key' => 'services.update_children', 'label' => 'تعديل الخدمات التي أنشأها التابعون'],
        'update_self' => ['key' => 'services.update_self', 'label' => 'تعديل الخدمات الخاصة بالمستخدم'],
        // صلاحيات الحذف (Delete)
        'delete_any' => ['key' => 'services.delete_any', 'label' => 'حذف أي خدمة'],
        'delete_children' => ['key' => 'services.delete_children', 'label' => 'حذف الخدمات التي أنشأها التابعون'],
        'delete_self' => ['key' => 'services.delete_self', 'label' => 'حذف الخدمات الخاصة بالمستخدم'],
    ],
    // => SUBSCRIPTIONS
    'subscriptions' => [
        'name' => ['key' => 'subscriptions', 'label' => 'صلاحيات إدارة الاشتراكات'],
        'page' => ['key' => 'subscriptions.page', 'label' => 'الوصول إلى صفحة الاشتراكات'],
        // صلاحيات العرض (View)
        'view_all' => ['key' => 'subscriptions.view_all', 'label' => 'عرض جميع الاشتراكات'],
        'view_children' => ['key' => 'subscriptions.view_children', 'label' => 'عرض الاشتراكات التي أنشأها التابعون'],
        'view_self' => ['key' => 'subscriptions.view_self', 'label' => 'عرض الاشتراكات الخاصة بالمستخدم'],
        // صلاحيات الإنشاء (Create)
        'create' => ['key' => 'subscriptions.create', 'label' => 'إنشاء اشتراك جديد'],
        // صلاحيات التعديل (Update)
        'update_any' => ['key' => 'subscriptions.update_any', 'label' => 'تعديل أي اشتراك'],
        'update_children' => ['key' => 'subscriptions.update_children', 'label' => 'تعديل الاشتراكات التي أنشأها التابعون'],
        'update_self' => ['key' => 'subscriptions.update_self', 'label' => 'تعديل الاشتراكات الخاصة بالمستخدم'],
        // صلاحيات الحذف (Delete)
        'delete_any' => ['key' => 'subscriptions.delete_any', 'label' => 'حذف أي اشتراك'],
        'delete_children' => ['key' => 'subscriptions.delete_children', 'label' => 'حذف الاشتراكات التي أنشأها التابعون'],
        'delete_self' => ['key' => 'subscriptions.delete_self', 'label' => 'حذف الاشتراكات الخاصة بالمستخدم'],
    ],
    // => ROLES
    'roles' => [
        'name' => ['key' => 'roles', 'label' => 'صلاحيات إدارة الأدوار'],
        'page' => ['key' => 'roles.page', 'label' => 'الوصول إلى صفحة الأدوار'],
        // صلاحيات العرض (View)
        'view_all' => ['key' => 'roles.view_all', 'label' => 'عرض جميع الأدوار'],
        'view_children' => ['key' => 'roles.view_children', 'label' => 'عرض الأدوار التي أنشأها التابعون'],
        'view_self' => ['key' => 'roles.view_self', 'label' => 'عرض الأدوار الخاصة بالمستخدم'],
        // صلاحيات الإنشاء (Create)
        'create' => ['key' => 'roles.create', 'label' => 'إنشاء دور جديد'],
        // صلاحيات التعديل (Update)
        'update_any' => ['key' => 'roles.update_any', 'label' => 'تعديل أي دور'],
        'update_children' => ['key' => 'roles.update_children', 'label' => 'تعديل الأدوار التي أنشأها التابعون'],
        'update_self' => ['key' => 'roles.update_self', 'label' => 'تعديل الأدوار الخاصة بالمستخدم'],
        // صلاحيات الحذف (Delete)
        'delete_any' => ['key' => 'roles.delete_any', 'label' => 'حذف أي دور'],
        'delete_children' => ['key' => 'roles.delete_children', 'label' => 'حذف الأدوار التي أنشأها التابعون'],
        'delete_self' => ['key' => 'roles.delete_self', 'label' => 'حذف الأدوار الخاصة بالمستخدم'],
    ],
];
