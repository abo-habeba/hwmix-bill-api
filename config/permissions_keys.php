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
    // =====================================================================
    // ADMIN
    // =====================================================================
    'admin' => [
        'name' => ['key' => 'admin', 'label' => 'صلاحيات المديرين'],
        'page' => ['key' => 'admin.page', 'label' => 'الصفحة الرئيسية'],
        'super' => ['key' => 'admin.super', 'label' => ' صلاحية المدير العام'],
        'company' => ['key' => 'admin.company', 'label' => 'صلاحية ادارة الشركة'],
    ],
    // =====================================================================
    // COMPANIES
    // =====================================================================
    'companies' => [
        'name' => ['key' => 'companies', 'label' => 'صلاحيات إدارة الشركات'],
        'change_active_company' => ['key' => 'companies.change_active_company', 'label' => 'تغيير الشركة النشطة'],
        'page' => ['key' => 'companies.page', 'label' => 'صفحة الشركات'],
        'view_all' => ['key' => 'companies.view_all', 'label' => 'عرض كل الشركات'],
        'view_children' => ['key' => 'companies.view_children', 'label' => 'عرض الشركات التابعة'],
        'view_self' => ['key' => 'companies.view_self', 'label' => 'عرض الشركة الحالية'],
        'create' => ['key' => 'companies.create', 'label' => 'إنشاء شركة'],
        'update_any' => ['key' => 'companies.update_any', 'label' => 'تعديل أى شركة'],
        'update_children' => ['key' => 'companies.update_children', 'label' => 'تعديل الشركات التابعة'],
        'update_self' => ['key' => 'companies.update_self', 'label' => 'تعديل الشركة الحالية'],
        'delete_any' => ['key' => 'companies.delete_any', 'label' => 'حذف أى شركة'],
        'delete_children' => ['key' => 'companies.delete_children', 'label' => 'حذف الشركات التابعة'],
        'delete_self' => ['key' => 'companies.delete_self', 'label' => 'حذف الشركة الحالية'],
    ],
    // =====================================================================
    // USERS
    // =====================================================================
    'users' => [
        'name' => ['key' => 'users', 'label' => 'صلاحيات إدارة المستخدمين'],
        'page' => ['key' => 'users.page', 'label' => 'صفحة المستخدمين'],
        'view_all' => ['key' => 'users.view_all', 'label' => 'عرض كل المستخدمين'],
        'view_children' => ['key' => 'users.view_children', 'label' => 'عرض المستخدمين التابعين'],
        'view_self' => ['key' => 'users.view_self', 'label' => 'عرض الحساب الشخصى'],
        'create' => ['key' => 'users.create', 'label' => 'إنشاء مستخدم'],
        'update_any' => ['key' => 'users.update_any', 'label' => 'تعديل أى مستخدم'],
        'update_children' => ['key' => 'users.update_children', 'label' => 'تعديل التابعين'],
        'update_self' => ['key' => 'users.update_self', 'label' => 'تعديل حسابه'],
        'delete_any' => ['key' => 'users.delete_any', 'label' => 'حذف أى مستخدم'],
        'delete_children' => ['key' => 'users.delete_children', 'label' => 'حذف التابعين'],
        'delete_self' => ['key' => 'users.delete_self', 'label' => 'حذف حسابه'],
    ],
    // =====================================================================
    // PERSONAL ACCESS TOKENS
    // =====================================================================
    'personal_access_tokens' => [
        'name' => ['key' => 'personal_access_tokens', 'label' => 'صلاحيات إدارة رموز الوصول الشخصية'],
        'page' => ['key' => 'personal_access_tokens.page', 'label' => 'صفحة رموز الوصول الشخصية'],
        'view_any' => ['key' => 'personal_access_tokens.view_any', 'label' => 'عرض كل رموز الوصول'],
        'view' => ['key' => 'personal_access_tokens.view', 'label' => 'عرض تفاصيل رمز الوصول'],
        'create' => ['key' => 'personal_access_tokens.create', 'label' => 'إنشاء رمز وصول'],
        'delete_any' => ['key' => 'personal_access_tokens.delete_any', 'label' => 'حذف أى رمز وصول'],
    ],
    // =====================================================================
    // TRANSLATIONS
    // =====================================================================
    'translations' => [
        'name' => ['key' => 'translations', 'label' => 'صلاحيات إدارة الترجمات'],
        'page' => ['key' => 'translations.page', 'label' => 'صفحة الترجمات'],
        'view_any' => ['key' => 'translations.view_any', 'label' => 'عرض كل الترجمات'],
        'update_any' => ['key' => 'translations.update_any', 'label' => 'تعديل أى ترجمة'],
    ],
    // =====================================================================
    // TRANSACTIONS
    // =====================================================================
    'transactions' => [
        'name' => ['key' => 'transactions', 'label' => 'صلاحيات إدارة المعاملات'],
        'page' => ['key' => 'transactions.page', 'label' => 'الوصول إلى صفحة المعاملات'],

        // صلاحيات العرض (View)
        'view_any' => ['key' => 'transactions.view_any', 'label' => 'عرض جميع المعاملات (ضمن الشركة)'],
        'view_children' => ['key' => 'transactions.view_children', 'label' => 'عرض المعاملات التي أنشأها المستخدمون التابعون'],
        'view_self' => ['key' => 'transactions.view_self', 'label' => 'عرض المعاملات التي أنشأها المستخدم فقط'],
        'view' => ['key' => 'transactions.view', 'label' => 'عرض تفاصيل معاملة محددة (تتطلب إحدى صلاحيات العرض الأخرى)'],

        // صلاحيات الإنشاء (Create)
        'create' => ['key' => 'transactions.create', 'label' => 'إنشاء معاملة جديدة'],

        // صلاحيات التعديل (Update)
        'update_any' => ['key' => 'transactions.update_any', 'label' => 'تعديل أي معاملة (ضمن الشركة)'],
        'update_children' => ['key' => 'transactions.update_children', 'label' => 'تعديل المعاملات التي أنشأها المستخدمون التابعون'],
        'update_self' => ['key' => 'transactions.update_self', 'label' => 'تعديل المعاملات التي أنشأها المستخدم فقط'],

        // صلاحيات الحذف (Delete)
        'delete_any' => ['key' => 'transactions.delete_any', 'label' => 'حذف أي معاملة (ضمن الشركة)'],
        'delete_children' => ['key' => 'transactions.delete_children', 'label' => 'حذف المعاملات التي أنشأها المستخدمون التابعون'],
        'delete_self' => ['key' => 'transactions.delete_self', 'label' => 'حذف المعاملات التي أنشأها المستخدم فقط'],
    ],
    // =====================================================================
    // ACTIVITY LOGS
    // =====================================================================
    'activity_logs' => [
        'name' => [
            'key' => 'activity_logs',
            'label' => 'سجل النشاطات'
        ],
        'page' => [
            'key' => 'activity_logs.page',
            'label' => 'الوصول إلى صفحة سجل النشاطات',
        ],
        'view_all' => [
            'key' => 'activity_logs.view_all',
            'label' => 'عرض كافة سجلات النشاطات',
        ],
        'view_children' => [
            'key' => 'activity_logs.view_children',
            'label' => 'عرض السجلات التي أنشأها المستخدم والتابعون له',
        ],
        'view_self' => [
            'key' => 'activity_logs.view_self',
            'label' => 'عرض سجل النشاطات الخاصة بالمستخدم فقط',
        ],
        'delete_any' => [
            'key' => 'activity_logs.delete_any',
            'label' => 'حذف أي سجل من سجل النشاطات',
        ],
        'delete_children' => [
            'key' => 'activity_logs.delete_children',
            'label' => 'حذف سجلات أنشأها المستخدمون التابعون',
        ],
        'delete_self' => [
            'key' => 'activity_logs.delete_self',
            'label' => 'حذف سجلات النشاطات الخاصة بالمستخدم فقط',
        ],
    ],
    // =====================================================================
    // CASH BOX TYPES
    // =====================================================================
    'cash_box_types' => [
        'name' => ['key' => 'cash_box_types', 'label' => 'صلاحيات إدارة أنواع الصناديق النقدية'],
        'page' => ['key' => 'cash_box_types.page', 'label' => 'صفحة أنواع الصناديق النقدية'],
        'view_any' => ['key' => 'cash_box_types.view_any', 'label' => 'عرض كل أنواع الصناديق'],
        'view' => ['key' => 'cash_box_types.view', 'label' => 'عرض تفاصيل نوع الصندوق'],
        'create' => ['key' => 'cash_box_types.create', 'label' => 'إنشاء نوع صندوق'],
        'update_any' => ['key' => 'cash_box_types.update_any', 'label' => 'تعديل أى نوع صندوق'],
        'delete_any' => ['key' => 'cash_box_types.delete_any', 'label' => 'حذف أى نوع صندوق'],
    ],
    // =====================================================================
    // CASH BOXES
    // =====================================================================
    'cash_boxes' => [
        'name' => ['key' => 'cash_boxes', 'label' => 'صلاحيات إدارة الصناديق النقدية'],
        'page' => ['key' => 'cash_boxes.page', 'label' => 'الوصول إلى صفحة الصناديق النقدية'],

        // صلاحيات العرض (View)
        'view_any' => ['key' => 'cash_boxes.view_any', 'label' => 'عرض جميع الصناديق النقدية (ضمن الشركة)'],
        'view_children' => ['key' => 'cash_boxes.view_children', 'label' => 'عرض الصناديق النقدية التي أنشأها المستخدمون التابعون'],
        'view_self' => ['key' => 'cash_boxes.view_self', 'label' => 'عرض الصناديق النقدية التي أنشأها المستخدم فقط'],
        'view' => ['key' => 'cash_boxes.view', 'label' => 'عرض تفاصيل صندوق نقدي محدد (تتطلب إحدى صلاحيات العرض الأخرى)'],

        // صلاحيات الإنشاء (Create)
        'create' => ['key' => 'cash_boxes.create', 'label' => 'إنشاء صندوق نقدي جديد'],

        // صلاحيات التعديل (Update)
        'update_any' => ['key' => 'cash_boxes.update_any', 'label' => 'تعديل أي صندوق نقدي (ضمن الشركة)'],
        'update_children' => ['key' => 'cash_boxes.update_children', 'label' => 'تعديل الصناديق النقدية التي أنشأها المستخدمون التابعون'],
        'update_self' => ['key' => 'cash_boxes.update_self', 'label' => 'تعديل الصناديق النقدية التي أنشأها المستخدم فقط'],

        // صلاحيات الحذف (Delete)
        'delete_any' => ['key' => 'cash_boxes.delete_any', 'label' => 'حذف أي صندوق نقدي (ضمن الشركة)'],
        'delete_children' => ['key' => 'cash_boxes.delete_children', 'label' => 'حذف الصناديق النقدية التي أنشأها المستخدمون التابعون'],
        'delete_self' => ['key' => 'cash_boxes.delete_self', 'label' => 'حذف الصناديق النقدية التي أنشأها المستخدم فقط'],
    ],
    // =====================================================================
    // IMAGES
    // =====================================================================
    'images' => [
        'name' => ['key' => 'images', 'label' => 'صلاحيات إدارة الصور'],
        'page' => ['key' => 'images.page', 'label' => 'صفحة الصور'],
        'view_any' => ['key' => 'images.view_any', 'label' => 'عرض كل الصور'],
        'view' => ['key' => 'images.view', 'label' => 'عرض تفاصيل الصورة'],
        'create' => ['key' => 'images.create', 'label' => 'رفع صورة'],
        'delete_any' => ['key' => 'images.delete_any', 'label' => 'حذف أى صورة'],
    ],
    // =====================================================================
    // WAREHOUSES
    // =====================================================================
    'warehouses' => [
        'name' => ['key' => 'warehouses', 'label' => 'صلاحيات إدارة المستودعات'],
        'page' => ['key' => 'warehouses.page', 'label' => 'الوصول إلى صفحة المستودعات'],

        // صلاحيات العرض (View)
        'view_any' => ['key' => 'warehouses.view_any', 'label' => 'عرض جميع المستودعات (ضمن الشركة)'],
        'view_children' => ['key' => 'warehouses.view_children', 'label' => 'عرض المستودعات التي أنشأها المستخدمون التابعون'],
        'view_self' => ['key' => 'warehouses.view_self', 'label' => 'عرض المستودعات التي أنشأها المستخدم فقط'],
        'view' => ['key' => 'warehouses.view', 'label' => 'عرض تفاصيل مستودع محدد (تتطلب إحدى صلاحيات العرض الأخرى)'],

        // صلاحيات الإنشاء (Create)
        'create' => ['key' => 'warehouses.create', 'label' => 'إنشاء مستودع جديد'],

        // صلاحيات التعديل (Update)
        'update_any' => ['key' => 'warehouses.update_any', 'label' => 'تعديل أي مستودع (ضمن الشركة)'],
        'update_children' => ['key' => 'warehouses.update_children', 'label' => 'تعديل المستودعات التي أنشأها المستخدمون التابعون'],
        'update_self' => ['key' => 'warehouses.update_self', 'label' => 'تعديل المستودعات التي أنشأها المستخدم فقط'],

        // صلاحيات الحذف (Delete)
        'delete_any' => ['key' => 'warehouses.delete_any', 'label' => 'حذف أي مستودع (ضمن الشركة)'],
        'delete_children' => ['key' => 'warehouses.delete_children', 'label' => 'حذف المستودعات التي أنشأها المستخدمون التابعون'],
        'delete_self' => ['key' => 'warehouses.delete_self', 'label' => 'حذف المستودعات التي أنشأها المستخدم فقط'],
    ],
    // =====================================================================
    // CATEGORIES
    // =====================================================================
    'categories' => [
        'name' => ['key' => 'categories', 'label' => 'صلاحيات إدارة الفئات'],
        'page' => ['key' => 'categories.page', 'label' => 'صفحة الفئات'],
        'view_any' => ['key' => 'categories.view_any', 'label' => 'عرض كل الفئات'],
        'view' => ['key' => 'categories.view', 'label' => 'عرض تفاصيل الفئة'],
        'create' => ['key' => 'categories.create', 'label' => 'إنشاء فئة'],
        'update_any' => ['key' => 'categories.update_any', 'label' => 'تعديل أى فئة'],
        'delete_any' => ['key' => 'categories.delete_any', 'label' => 'حذف أى فئة'],
    ],
    // =====================================================================
    // BRANDS
    // =====================================================================
    'brands' => [
        'name' => ['key' => 'brands', 'label' => 'صلاحيات إدارة الماركات'],
        'page' => ['key' => 'brands.page', 'label' => 'صفحة الماركات'],
        'view_any' => ['key' => 'brands.view_any', 'label' => 'عرض كل الماركات'],
        'view' => ['key' => 'brands.view', 'label' => 'عرض تفاصيل الماركة'],
        'create' => ['key' => 'brands.create', 'label' => 'إنشاء ماركة'],
        'update_any' => ['key' => 'brands.update_any', 'label' => 'تعديل أى ماركة'],
        'delete_any' => ['key' => 'brands.delete_any', 'label' => 'حذف أى ماركة'],
    ],
    // =====================================================================
    // ATTRIBUTES
    // =====================================================================
    'attributes' => [
        'name' => ['key' => 'attributes', 'label' => 'صلاحيات إدارة السمات'],
        'page' => ['key' => 'attributes.page', 'label' => 'صفحة السمات'],
        'view_any' => ['key' => 'attributes.view_any', 'label' => 'عرض كل السمات'],
        'view' => ['key' => 'attributes.view', 'label' => 'عرض تفاصيل السمة'],
        'create' => ['key' => 'attributes.create', 'label' => 'إنشاء سمة'],
        'update_any' => ['key' => 'attributes.update_any', 'label' => 'تعديل أى سمة'],
        'delete_any' => ['key' => 'attributes.delete_any', 'label' => 'حذف أى سمة'],
    ],
    // =====================================================================
    // ATTRIBUTE VALUES
    // =====================================================================
    'attribute_values' => [
        'name' => ['key' => 'attribute_values', 'label' => 'صلاحيات إدارة قيم السمات'],
        'page' => ['key' => 'attribute_values.page', 'label' => 'صفحة قيم السمات'],
        'view_any' => ['key' => 'attribute_values.view_any', 'label' => 'عرض كل قيم السمات'],
        'view' => ['key' => 'attribute_values.view', 'label' => 'عرض تفاصيل قيمة السمة'],
        'create' => ['key' => 'attribute_values.create', 'label' => 'إنشاء قيمة سمة'],
        'update_any' => ['key' => 'attribute_values.update_any', 'label' => 'تعديل أى قيمة سمة'],
        'delete_any' => ['key' => 'attribute_values.delete_any', 'label' => 'حذف أى قيمة سمة'],
    ],
    // =====================================================================
    // PRODUCTS
    // =====================================================================
    'products' => [
        'name' => ['key' => 'products', 'label' => 'صلاحيات إدارة المنتجات'],
        'page' => ['key' => 'products.page', 'label' => 'الوصول إلى صفحة المنتجات'],

        // صلاحيات العرض (View)
        'view_any' => ['key' => 'products.view_any', 'label' => 'عرض جميع المنتجات (ضمن الشركة)'],
        'view_children' => ['key' => 'products.view_children', 'label' => 'عرض المنتجات التي أنشأها المستخدمون التابعون'],
        'view_self' => ['key' => 'products.view_self', 'label' => 'عرض المنتجات التي أنشأها المستخدم فقط'],
        'view' => ['key' => 'products.view', 'label' => 'عرض تفاصيل منتج محدد (تتطلب إحدى صلاحيات العرض الأخرى)'],

        // صلاحيات الإنشاء (Create)
        'create' => ['key' => 'products.create', 'label' => 'إنشاء منتج جديد'],

        // صلاحيات التعديل (Update)
        'update_any' => ['key' => 'products.update_any', 'label' => 'تعديل أي منتج (ضمن الشركة)'],
        'update_children' => ['key' => 'products.update_children', 'label' => 'تعديل المنتجات التي أنشأها المستخدمون التابعون'],
        'update_self' => ['key' => 'products.update_self', 'label' => 'تعديل المنتجات التي أنشأها المستخدم فقط'],

        // صلاحيات الحذف (Delete)
        'delete_any' => ['key' => 'products.delete_any', 'label' => 'حذف أي منتج (ضمن الشركة)'],
        'delete_children' => ['key' => 'products.delete_children', 'label' => 'حذف المنتجات التي أنشأها المستخدمون التابعون'],
        'delete_self' => ['key' => 'products.delete_self', 'label' => 'حذف المنتجات التي أنشأها المستخدم فقط'],
    ],
    // =====================================================================
    // PRODUCT VARIANTS
    // =====================================================================
    'product_variants' => [
        'name' => ['key' => 'product_variants', 'label' => 'صلاحيات إدارة متغيرات المنتجات'],
        'page' => ['key' => 'product_variants.page', 'label' => 'صفحة متغيرات المنتجات'],
        'view_any' => ['key' => 'product_variants.view_any', 'label' => 'عرض كل متغيرات المنتجات'],
        'view' => ['key' => 'product_variants.view', 'label' => 'عرض تفاصيل متغير المنتج'],
        'create' => ['key' => 'product_variants.create', 'label' => 'إنشاء متغير منتج'],
        'update_any' => ['key' => 'product_variants.update_any', 'label' => 'تعديل أى متغير منتج'],
        'delete_any' => ['key' => 'product_variants.delete_any', 'label' => 'حذف أى متغير منتج'],
    ],
    // =====================================================================
    // PRODUCT VARIANT ATTRIBUTES
    // =====================================================================
    'product_variant_attributes' => [
        'name' => ['key' => 'product_variant_attributes', 'label' => 'صلاحيات إدارة سمات متغيرات المنتجات'],
        'page' => ['key' => 'product_variant_attributes.page', 'label' => 'صفحة سمات متغيرات المنتجات'],
        'view_any' => ['key' => 'product_variant_attributes.view_any', 'label' => 'عرض كل سمات متغيرات المنتجات'],
        'view' => ['key' => 'product_variant_attributes.view', 'label' => 'عرض تفاصيل سمة متغير المنتج'],
        'create' => ['key' => 'product_variant_attributes.create', 'label' => 'إنشاء سمة لمتغير منتج'],
        'update_any' => ['key' => 'product_variant_attributes.update_any', 'label' => 'تعديل أى سمة لمتغير منتج'],
        'delete_any' => ['key' => 'product_variant_attributes.delete_any', 'label' => 'حذف أى سمة لمتغير منتج'],
    ],
    // =====================================================================
    // STOCKS
    // =====================================================================
    'stocks' => [
        'name' => ['key' => 'stocks', 'label' => 'صلاحيات إدارة المخزون'],
        'page' => ['key' => 'stocks.page', 'label' => 'صفحة المخزون'],
        'view_any' => ['key' => 'stocks.view_any', 'label' => 'عرض كل المخزون'],
        'view' => ['key' => 'stocks.view', 'label' => 'عرض تفاصيل المخزون'],
        'create' => ['key' => 'stocks.create', 'label' => 'إنشاء إدخال مخزون'],
        'update_any' => ['key' => 'stocks.update_any', 'label' => 'تعديل أى إدخال مخزون'],
        'delete_any' => ['key' => 'stocks.delete_any', 'label' => 'حذف أى إدخال مخزون'],
    ],
    // =====================================================================
    // INVOICE TYPES
    // =====================================================================
    // 'invoice_types' => [
    //     'name' => ['key' => 'invoice_types', 'label' => 'صلاحيات إدارة أنواع الفواتير'],
    //     'page' => ['key' => 'invoice_types.page', 'label' => 'صفحة أنواع الفواتير'],
    //     'view_any' => ['key' => 'invoice_types.view_any', 'label' => 'عرض كل أنواع الفواتير'],
    //     'view' => ['key' => 'invoice_types.view', 'label' => 'عرض تفاصيل نوع الفاتورة'],
    //     'create' => ['key' => 'invoice_types.create', 'label' => 'إنشاء نوع فاتورة'],
    //     'update_any' => ['key' => 'invoice_types.update_any', 'label' => 'تعديل أى نوع فاتورة'],
    //     'delete_any' => ['key' => 'invoice_types.delete_any', 'label' => 'حذف أى نوع فاتورة'],
    // ],
    // =====================================================================
    // INVOICES
    // =====================================================================
    'invoices' => [
        'name' => ['key' => 'invoices', 'label' => 'صلاحيات إدارة الفواتير'],
        'page' => ['key' => 'invoices.page', 'label' => 'الوصول إلى صفحة الفواتير'],
        // صلاحيات العرض (View)
        'view_any' => ['key' => 'invoices.view_any', 'label' => 'عرض جميع الفواتير (ضمن الشركة)'],
        'view_children' => ['key' => 'invoices.view_children', 'label' => 'عرض الفواتير التي أنشأها المستخدمون التابعون'],
        'view_self' => ['key' => 'invoices.view_self', 'label' => 'عرض الفواتير التي أنشأها المستخدم فقط'],
        'view' => ['key' => 'invoices.view', 'label' => 'عرض تفاصيل فاتورة محددة (تتطلب إحدى صلاحيات العرض الأخرى)'],  // يمكن استخدامها للتحقق من الوصول إلى فاتورة فردية بعد الفلترة العامة
        // صلاحيات الإنشاء (Create)
        'create' => ['key' => 'invoices.create', 'label' => 'إنشاء فاتورة جديدة'],
        // صلاحيات التعديل (Update)
        'update_any' => ['key' => 'invoices.update_any', 'label' => 'تعديل أي فاتورة (ضمن الشركة)'],
        'update_children' => ['key' => 'invoices.update_children', 'label' => 'تعديل الفواتير التي أنشأها المستخدمون التابعون'],
        'update_self' => ['key' => 'invoices.update_self', 'label' => 'تعديل الفواتير التي أنشأها المستخدم فقط'],
        // صلاحيات الحذف (Delete)
        'delete_any' => ['key' => 'invoices.delete_any', 'label' => 'حذف أي فاتورة (ضمن الشركة)'],
        'delete_children' => ['key' => 'invoices.delete_children', 'label' => 'حذف الفواتير التي أنشأها المستخدمون التابعون'],
        'delete_self' => ['key' => 'invoices.delete_self', 'label' => 'حذف الفواتير التي أنشأها المستخدم فقط'],
    ],
    // =====================================================================
    // INSTALLMENT PLANS
    // =====================================================================
    'installment_plans' => [
        'name' => ['key' => 'installment_plans', 'label' => 'صلاحيات إدارة خطط التقسيط'],
        'page' => ['key' => 'installment_plans.page', 'label' => 'الوصول إلى صفحة خطط التقسيط'],

        // صلاحيات العرض (View)
        'view_any' => ['key' => 'installment_plans.view_any', 'label' => 'عرض جميع خطط التقسيط (ضمن الشركة)'],
        'view_children' => ['key' => 'installment_plans.view_children', 'label' => 'عرض خطط التقسيط التي أنشأها المستخدمون التابعون'],
        'view_self' => ['key' => 'installment_plans.view_self', 'label' => 'عرض خطط التقسيط التي أنشأها المستخدم فقط'],
        'view' => ['key' => 'installment_plans.view', 'label' => 'عرض تفاصيل خطة تقسيط محددة (تتطلب إحدى صلاحيات العرض الأخرى)'],

        // صلاحيات الإنشاء (Create)
        'create' => ['key' => 'installment_plans.create', 'label' => 'إنشاء خطة تقسيط جديدة'],

        // صلاحيات التعديل (Update)
        'update_any' => ['key' => 'installment_plans.update_any', 'label' => 'تعديل أي خطة تقسيط (ضمن الشركة)'],
        'update_children' => ['key' => 'installment_plans.update_children', 'label' => 'تعديل خطط التقسيط التي أنشأها المستخدمون التابعون'],
        'update_self' => ['key' => 'installment_plans.update_self', 'label' => 'تعديل خطط التقسيط التي أنشأها المستخدم فقط'],

        // صلاحيات الحذف (Delete)
        'delete_any' => ['key' => 'installment_plans.delete_any', 'label' => 'حذف أي خطة تقسيط (ضمن الشركة)'],
        'delete_children' => ['key' => 'installment_plans.delete_children', 'label' => 'حذف خطط التقسيط التي أنشأها المستخدمون التابعون'],
        'delete_self' => ['key' => 'installment_plans.delete_self', 'label' => 'حذف خطط التقسيط التي أنشأها المستخدم فقط'],
    ],
    // =====================================================================
    // INSTALLMENTS
    // =====================================================================
    'installments' => [
        'name' => ['key' => 'installments', 'label' => 'صلاحيات إدارة الأقساط'],
        'page' => ['key' => 'installments.page', 'label' => 'الوصول إلى صفحة الأقساط'],
        // صلاحيات العرض (View)
        'view_any' => ['key' => 'installments.view_any', 'label' => 'عرض جميع الأقساط (ضمن الشركة)'],
        'view_children' => ['key' => 'installments.view_children', 'label' => 'عرض الأقساط التي أنشأها المستخدمون التابعون'],
        'view_self' => ['key' => 'installments.view_self', 'label' => 'عرض الأقساط التي أنشأها المستخدم فقط'],
        'view' => ['key' => 'installments.view', 'label' => 'عرض تفاصيل قسط محدد (تتطلب إحدى صلاحيات العرض الأخرى)'],  // يمكن استخدامها للتحقق من الوصول إلى قسط فردي بعد الفلترة العامة
        // صلاحيات الإنشاء (Create)
        'create' => ['key' => 'installments.create', 'label' => 'إنشاء قسط جديد'],
        // صلاحيات التعديل (Update)
        'update_any' => ['key' => 'installments.update_any', 'label' => 'تعديل أي قسط (ضمن الشركة)'],
        'update_children' => ['key' => 'installments.update_children', 'label' => 'تعديل الأقساط التي أنشأها المستخدمون التابعون'],
        'update_self' => ['key' => 'installments.update_self', 'label' => 'تعديل الأقساط التي أنشأها المستخدم فقط'],
        // صلاحيات الحذف (Delete)
        'delete_any' => ['key' => 'installments.delete_any', 'label' => 'حذف أي قسط (ضمن الشركة)'],
        'delete_children' => ['key' => 'installments.delete_children', 'label' => 'حذف الأقساط التي أنشأها المستخدمون التابعون'],
        'delete_self' => ['key' => 'installments.delete_self', 'label' => 'حذف الأقساط التي أنشأها المستخدم فقط'],
    ],
    // =====================================================================
    // INSTALLMENT PAYMENTS
    // =====================================================================
    'installment_payments' => [
        'name' => ['key' => 'installment_payments', 'label' => 'صلاحيات إدارة مدفوعات الأقساط'],
        'page' => ['key' => 'installment_payments.page', 'label' => 'صفحة مدفوعات الأقساط'],
        'view_any' => ['key' => 'installment_payments.view_any', 'label' => 'عرض كل مدفوعات الأقساط'],
        'view' => ['key' => 'installment_payments.view', 'label' => 'عرض تفاصيل دفع القسط'],
        'create' => ['key' => 'installment_payments.create', 'label' => 'إنشاء دفع قسط'],
        'update_any' => ['key' => 'installment_payments.update_any', 'label' => 'تعديل أى دفع قسط'],
        'delete_any' => ['key' => 'installment_payments.delete_any', 'label' => 'حذف أى دفع قسط'],
    ],
    // =====================================================================
    // INVOICE ITEMS
    // =====================================================================
    'invoice_items' => [
        'name' => ['key' => 'invoice_items', 'label' => 'صلاحيات إدارة عناصر الفواتير'],
        'page' => ['key' => 'invoice_items.page', 'label' => 'صفحة عناصر الفواتير'],
        'view_any' => ['key' => 'invoice_items.view_any', 'label' => 'عرض كل عناصر الفواتير'],
        'view' => ['key' => 'invoice_items.view', 'label' => 'عرض تفاصيل عنصر الفاتورة'],
        'create' => ['key' => 'invoice_items.create', 'label' => 'إنشاء عنصر فاتورة'],
        'update_any' => ['key' => 'invoice_items.update_any', 'label' => 'تعديل أى عنصر فاتورة'],
        'delete_any' => ['key' => 'invoice_items.delete_any', 'label' => 'حذف أى عنصر فاتورة'],
    ],
    // =====================================================================
    // PAYMENTS
    // =====================================================================
    'payments' => [
        'name' => ['key' => 'payments', 'label' => 'صلاحيات إدارة المدفوعات'],
        'page' => ['key' => 'payments.page', 'label' => 'صفحة المدفوعات'],
        'view_any' => ['key' => 'payments.view_any', 'label' => 'عرض كل المدفوعات'],
        'view' => ['key' => 'payments.view', 'label' => 'عرض تفاصيل الدفعة'],
        'create' => ['key' => 'payments.create', 'label' => 'إنشاء دفعة'],
        'update_any' => ['key' => 'payments.update_any', 'label' => 'تعديل أى دفعة'],
        'delete_any' => ['key' => 'payments.delete_any', 'label' => 'حذف أى دفعة'],
    ],
    // =====================================================================
    // PAYMENT METHODS
    // =====================================================================
    'payment_methods' => [
        'name' => ['key' => 'payment_methods', 'label' => 'صلاحيات إدارة طرق الدفع'],
        'page' => ['key' => 'payment_methods.page', 'label' => 'صفحة طرق الدفع'],
        'view_any' => ['key' => 'payment_methods.view_any', 'label' => 'عرض كل طرق الدفع'],
        'view' => ['key' => 'payment_methods.view', 'label' => 'عرض تفاصيل طريقة الدفع'],
        'create' => ['key' => 'payment_methods.create', 'label' => 'إنشاء طريقة دفع'],
        'update_any' => ['key' => 'payment_methods.update_any', 'label' => 'تعديل أى طريقة دفع'],
        'delete_any' => ['key' => 'payment_methods.delete_any', 'label' => 'حذف أى طريقة دفع'],
    ],
    // =====================================================================
    // REVENUES
    // =====================================================================
    'revenues' => [
        'name' => ['key' => 'revenues', 'label' => 'صلاحيات إدارة الإيرادات'],
        'page' => ['key' => 'revenues.page', 'label' => 'صفحة الإيرادات'],
        'view_any' => ['key' => 'revenues.view_any', 'label' => 'عرض كل الإيرادات'],
        'view' => ['key' => 'revenues.view', 'label' => 'عرض تفاصيل الإيراد'],
        'create' => ['key' => 'revenues.create', 'label' => 'تسجيل إيراد'],
        'update_any' => ['key' => 'revenues.update_any', 'label' => 'تعديل أى إيراد'],
        'delete_any' => ['key' => 'revenues.delete_any', 'label' => 'حذف أى إيراد'],
    ],
    // =====================================================================
    // PROFITS
    // =====================================================================
    'profits' => [
        'name' => ['key' => 'profits', 'label' => 'صلاحيات الأرباح'],
        'page' => ['key' => 'profits.page', 'label' => 'صفحة الأرباح'],
        'view_any' => ['key' => 'profits.view_any', 'label' => 'عرض كل الأرباح'],
        'view' => ['key' => 'profits.view', 'label' => 'عرض تفاصيل الربح'],
    ],
    // =====================================================================
    // SERVICES
    // =====================================================================
    'services' => [
        'name' => ['key' => 'services', 'label' => 'صلاحيات إدارة الخدمات'],
        'page' => ['key' => 'services.page', 'label' => 'صفحة الخدمات'],
        'view_any' => ['key' => 'services.view_any', 'label' => 'عرض كل الخدمات'],
        'view' => ['key' => 'services.view', 'label' => 'عرض تفاصيل الخدمة'],
        'create' => ['key' => 'services.create', 'label' => 'إنشاء خدمة'],
        'update_any' => ['key' => 'services.update_any', 'label' => 'تعديل أى خدمة'],
        'delete_any' => ['key' => 'services.delete_any', 'label' => 'حذف أى خدمة'],
    ],
    // =====================================================================
    // SUBSCRIPTIONS
    // =====================================================================
    'subscriptions' => [
        'name' => ['key' => 'subscriptions', 'label' => 'صلاحيات إدارة الاشتراكات'],
        'page' => ['key' => 'subscriptions.page', 'label' => 'صفحة الاشتراكات'],
        'view_any' => ['key' => 'subscriptions.view_any', 'label' => 'عرض كل الاشتراكات'],
        'view' => ['key' => 'subscriptions.view', 'label' => 'عرض تفاصيل الاشتراك'],
        'create' => ['key' => 'subscriptions.create', 'label' => 'إنشاء اشتراك'],
        'update_any' => ['key' => 'subscriptions.update_any', 'label' => 'تعديل أى اشتراك'],
        'delete_any' => ['key' => 'subscriptions.delete_any', 'label' => 'حذف أى اشتراك'],
    ],
    // =====================================================================
    // ROLES
    // =====================================================================
    'roles' => [
        'name' => ['key' => 'roles', 'label' => 'صلاحيات إدارة الأدوار'],
        'page' => ['key' => 'roles.page', 'label' => 'صفحة الأدوار'],
        'view_all' => ['key' => 'roles.view_all', 'label' => 'عرض كل الأدوار'],
        'view_children' => ['key' => 'roles.view_children', 'label' => 'عرض الأدوار التابعة'],
        'view_self' => ['key' => 'roles.view_self', 'label' => 'عرض الأدوار الخاصة به'],
        'create' => ['key' => 'roles.create', 'label' => 'إنشاء دور'],
        'update_any' => ['key' => 'roles.update_any', 'label' => 'تعديل أى دور'],
        'update_children' => ['key' => 'roles.update_children', 'label' => 'تعديل الأدوار التابعة'],
        'update_self' => ['key' => 'roles.update_self', 'label' => 'تعديل دوره الخاص'],
        'delete_any' => ['key' => 'roles.delete_any', 'label' => 'حذف أى دور'],
        'delete_children' => ['key' => 'roles.delete_children', 'label' => 'حذف الأدوار التابعة'],
        'delete_self' => ['key' => 'roles.delete_self', 'label' => 'حذف دوره الخاص'],
    ],
];
