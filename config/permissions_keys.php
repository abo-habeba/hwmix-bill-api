<?php

/**
 * -----------------------------------------------------------------------------
 * Permission Keys Registry — Arabic Labels
 * -----------------------------------------------------------------------------
 * هذا الملف هو المصدر الوحيد لمفاتيح الصلاحيات (key) والتسميات (label).
 * الصيغة القياسية: entity.action  ⇠ تُحفظ فى جدول permissions
 * -----------------------------------------------------------------------------
 * actions الأساسية لكل كيان:
 * page | view_any | view_children | view_self | create | update_any | update_children | update_self | delete_any | delete_children | delete_self
 * -----------------------------------------------------------------------------
 */
return [
    // =====================================================================
    // COMPANIES
    // =====================================================================
    'companies' => [
        'page' => ['key' => 'companies.page', 'label' => 'صفحة الشركات'],
        'view_any' => ['key' => 'companies.view_any', 'label' => 'عرض كل الشركات'],
        'view' => ['key' => 'companies.view', 'label' => 'عرض تفاصيل الشركة'],
        'create' => ['key' => 'companies.create', 'label' => 'إنشاء شركة جديدة'],
        'update_any' => ['key' => 'companies.update_any', 'label' => 'تعديل أى شركة'],
        'delete_any' => ['key' => 'companies.delete_any', 'label' => 'حذف أى شركة'],
    ],
    // =====================================================================
    // USERS (مثال مقدم في سؤالك)
    // =====================================================================
    'users' => [
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
        'page' => ['key' => 'translations.page', 'label' => 'صفحة الترجمات'],
        'view_any' => ['key' => 'translations.view_any', 'label' => 'عرض كل الترجمات'],
        'update_any' => ['key' => 'translations.update_any', 'label' => 'تعديل أى ترجمة'],
    ],
    // =====================================================================
    // TRANSACTIONS
    // =====================================================================
    'transactions' => [
        'page' => ['key' => 'transactions.page', 'label' => 'صفحة المعاملات'],
        'view_any' => ['key' => 'transactions.view_any', 'label' => 'عرض كل المعاملات'],
        'view' => ['key' => 'transactions.view', 'label' => 'عرض تفاصيل المعاملة'],
        'create' => ['key' => 'transactions.create', 'label' => 'إنشاء معاملة'],
        'update_any' => ['key' => 'transactions.update_any', 'label' => 'تعديل أى معاملة'],
        'delete_any' => ['key' => 'transactions.delete_any', 'label' => 'حذف أى معاملة'],
    ],
    // =====================================================================
    // ACTIVITY LOGS
    // =====================================================================
    'activity_logs' => [
        'page' => ['key' => 'activity_logs.page', 'label' => 'صفحة سجلات النشاط'],
        'view_any' => ['key' => 'activity_logs.view_any', 'label' => 'عرض كل سجلات النشاط'],
        'view' => ['key' => 'activity_logs.view', 'label' => 'عرض تفاصيل سجل النشاط'],
    ],
    // =====================================================================
    // CASH BOX TYPES
    // =====================================================================
    'cash_box_types' => [
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
        'page' => ['key' => 'cash_boxes.page', 'label' => 'صفحة الصناديق النقدية'],
        'view_any' => ['key' => 'cash_boxes.view_any', 'label' => 'عرض كل الصناديق النقدية'],
        'view' => ['key' => 'cash_boxes.view', 'label' => 'عرض تفاصيل الصندوق النقدي'],
        'create' => ['key' => 'cash_boxes.create', 'label' => 'إنشاء صندوق نقدي'],
        'update_any' => ['key' => 'cash_boxes.update_any', 'label' => 'تعديل أى صندوق نقدي'],
        'delete_any' => ['key' => 'cash_boxes.delete_any', 'label' => 'حذف أى صندوق نقدي'],
    ],
    // =====================================================================
    // IMAGES
    // =====================================================================
    'images' => [
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
        'page' => ['key' => 'warehouses.page', 'label' => 'صفحة المستودعات'],
        'view_any' => ['key' => 'warehouses.view_any', 'label' => 'عرض كل المستودعات'],
        'view' => ['key' => 'warehouses.view', 'label' => 'عرض تفاصيل المستودع'],
        'create' => ['key' => 'warehouses.create', 'label' => 'إنشاء مستودع'],
        'update_any' => ['key' => 'warehouses.update_any', 'label' => 'تعديل أى مستودع'],
        'delete_any' => ['key' => 'warehouses.delete_any', 'label' => 'حذف أى مستودع'],
    ],
    // =====================================================================
    // CATEGORIES
    // =====================================================================
    'categories' => [
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
        'page' => ['key' => 'products.page', 'label' => 'صفحة المنتجات'],
        'view_any' => ['key' => 'products.view_any', 'label' => 'عرض كل المنتجات'],
        'view' => ['key' => 'products.view', 'label' => 'عرض تفاصيل المنتج'],
        'create' => ['key' => 'products.create', 'label' => 'إنشاء منتج'],
        'update_any' => ['key' => 'products.update_any', 'label' => 'تعديل أى منتج'],
        'delete_any' => ['key' => 'products.delete_any', 'label' => 'حذف أى منتج'],
    ],
    // =====================================================================
    // PRODUCT VARIANTS
    // =====================================================================
    'product_variants' => [
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
    'invoice_types' => [
        'page' => ['key' => 'invoice_types.page', 'label' => 'صفحة أنواع الفواتير'],
        'view_any' => ['key' => 'invoice_types.view_any', 'label' => 'عرض كل أنواع الفواتير'],
        'view' => ['key' => 'invoice_types.view', 'label' => 'عرض تفاصيل نوع الفاتورة'],
        'create' => ['key' => 'invoice_types.create', 'label' => 'إنشاء نوع فاتورة'],
        'update_any' => ['key' => 'invoice_types.update_any', 'label' => 'تعديل أى نوع فاتورة'],
        'delete_any' => ['key' => 'invoice_types.delete_any', 'label' => 'حذف أى نوع فاتورة'],
    ],
    // =====================================================================
    // INVOICES
    // =====================================================================
    'invoices' => [
        'page' => ['key' => 'invoices.page', 'label' => 'صفحة الفواتير'],
        'view_any' => ['key' => 'invoices.view_any', 'label' => 'عرض كل الفواتير'],
        'view' => ['key' => 'invoices.view', 'label' => 'عرض تفاصيل الفاتورة'],
        'create' => ['key' => 'invoices.create', 'label' => 'إنشاء فاتورة'],
        'update_any' => ['key' => 'invoices.update_any', 'label' => 'تعديل أى فاتورة'],
        'delete_any' => ['key' => 'invoices.delete_any', 'label' => 'حذف أى فاتورة'],
    ],
    // =====================================================================
    // INSTALLMENT PLANS
    // =====================================================================
    'installment_plans' => [
        'page' => ['key' => 'installment_plans.page', 'label' => 'صفحة خطط التقسيط'],
        'view_any' => ['key' => 'installment_plans.view_any', 'label' => 'عرض كل خطط التقسيط'],
        'view' => ['key' => 'installment_plans.view', 'label' => 'عرض تفاصيل خطة التقسيط'],
        'create' => ['key' => 'installment_plans.create', 'label' => 'إنشاء خطة تقسيط'],
        'update_any' => ['key' => 'installment_plans.update_any', 'label' => 'تعديل أى خطة تقسيط'],
        'delete_any' => ['key' => 'installment_plans.delete_any', 'label' => 'حذف أى خطة تقسيط'],
    ],
    // =====================================================================
    // INSTALLMENTS
    // =====================================================================
    'installments' => [
        'page' => ['key' => 'installments.page', 'label' => 'صفحة الأقساط'],
        'view_any' => ['key' => 'installments.view_any', 'label' => 'عرض كل الأقساط'],
        'view' => ['key' => 'installments.view', 'label' => 'عرض تفاصيل القسط'],
        'create' => ['key' => 'installments.create', 'label' => 'إنشاء قسط'],
        'update_any' => ['key' => 'installments.update_any', 'label' => 'تعديل أى قسط'],
        'delete_any' => ['key' => 'installments.delete_any', 'label' => 'حذف أى قسط'],
    ],
    // =====================================================================
    // INSTALLMENT PAYMENTS
    // =====================================================================
    'installment_payments' => [
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
        'page' => ['key' => 'profits.page', 'label' => 'صفحة الأرباح'],
        'view_any' => ['key' => 'profits.view_any', 'label' => 'عرض كل الأرباح'],
        'view' => ['key' => 'profits.view', 'label' => 'عرض تفاصيل الربح'],
    ],
    // =====================================================================
    // SERVICES
    // =====================================================================
    'services' => [
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
        'page' => ['key' => 'subscriptions.page', 'label' => 'صفحة الاشتراكات'],
        'view_any' => ['key' => 'subscriptions.view_any', 'label' => 'عرض كل الاشتراكات'],
        'view' => ['key' => 'subscriptions.view', 'label' => 'عرض تفاصيل الاشتراك'],
        'create' => ['key' => 'subscriptions.create', 'label' => 'إنشاء اشتراك'],
        'update_any' => ['key' => 'subscriptions.update_any', 'label' => 'تعديل أى اشتراك'],
        'delete_any' => ['key' => 'subscriptions.delete_any', 'label' => 'حذف أى اشتراك'],
    ],
];
