<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\InvoiceType;
use App\Models\Company;
use App\Models\User;

class InvoiceTypeSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::first();
        $user = User::first();
        $types = [
            [
                'name' => 'فاتورة بيع بالتقسيط',
                'description' => 'فاتورة بيع بالتقسيط (تستخدم لبيع المنتجات أو الخدمات مع جدول أقساط محدد، وتوليد سندات قبض/صرف مرتبطة بالأقساط، مع تحديث المخزون تلقائياً).',
                'code' => 'installment_sale',
                'context' => 'sales',
                'company_id' => $company?->id,
                'created_by' => $user?->id,
            ],
            [
                'name' => 'فاتورة بيع',
                'description' => 'فاتورة بيع للعميل (بيع منتجات أو خدمات للعميل النهائي، مع إمكانية الدفع نقداً أو بالتقسيط، وتوليد سندات قبض/صرف مرتبطة بالفاتورة، وتحديث المخزون تلقائياً).',
                'code' => 'sale',
                'context' => 'sales',
                'company_id' => $company?->id,
                'created_by' => $user?->id,
            ],
            [
                'name' => 'فاتورة شراء',
                'description' => 'فاتورة شراء من المورد (تستخدم لتسجيل مشتريات المنتجات أو الخدمات من الموردين، مع تحديث المخزون تلقائياً، وربط الفاتورة بطرق الدفع المختلفة).',
                'code' => 'purchase',
                'context' => 'purchases',
                'company_id' => $company?->id,
                'created_by' => $user?->id,
            ],
            [
                'name' => 'مرتجع بيع',
                'description' => 'مرتجع بيع للعميل (تستخدم لإرجاع منتجات تم بيعها سابقاً للعميل، مع تحديث المخزون وتوليد سندات مالية مرتبطة بالمرتجع).',
                'code' => 'sale_return',
                'context' => 'sales',
                'company_id' => $company?->id,
                'created_by' => $user?->id,
            ],
            [
                'name' => 'مرتجع شراء',
                'description' => 'مرتجع شراء من المورد (تستخدم لإرجاع منتجات تم شراؤها من المورد، مع تحديث المخزون وتوليد سندات مالية مرتبطة بالمرتجع).',
                'code' => 'purchase_return',
                'context' => 'purchases',
                'company_id' => $company?->id,
                'created_by' => $user?->id,
            ],
            [
                'name' => 'عرض سعر',
                'description' => 'عرض سعر للعميل (تستخدم لتقديم عرض أسعار للعميل قبل التحويل لفاتورة بيع، بدون تأثير على المخزون أو الحسابات المالية).',
                'code' => 'quotation',
                'context' => 'sales',
                'company_id' => $company?->id,
                'created_by' => $user?->id,
            ],
            [
                'name' => 'طلب شراء',
                'description' => 'طلب شراء من المورد (تستخدم لتقديم طلب شراء للمورد قبل التحويل لفاتورة شراء، بدون تأثير مباشر على المخزون).',
                'code' => 'purchase_order',
                'context' => 'purchases',
                'company_id' => $company?->id,
                'created_by' => $user?->id,
            ],
            [
                'name' => 'طلب بيع',
                'description' => 'طلب بيع للعميل (تستخدم لتسجيل طلبات العملاء قبل التحويل لفاتورة بيع، بدون تأثير مباشر على المخزون).',
                'code' => 'sales_order',
                'context' => 'sales',
                'company_id' => $company?->id,
                'created_by' => $user?->id,
            ],
            [
                'name' => 'تسوية مخزون',
                'description' => 'تسوية مخزون (تستخدم لتسوية الكميات الفعلية في المخزون مع النظام، مع تسجيل الفروقات في الأرباح أو الخسائر).',
                'code' => 'inventory_adjustment',
                'context' => 'inventory',
                'company_id' => $company?->id,
                'created_by' => $user?->id,
            ],
            [
                'name' => 'تحويل مخزني',
                'description' => 'تحويل مخزني بين المخازن (تستخدم لنقل المنتجات بين المخازن الداخلية، مع تحديث الكميات في كل مخزن).',
                'code' => 'stock_transfer',
                'context' => 'inventory',
                'company_id' => $company?->id,
                'created_by' => $user?->id,
            ],
            [
                'name' => 'فاتورة خدمة',
                'description' => 'فاتورة خدمة (تستخدم لبيع خدمات بدون منتجات ملموسة، مع إمكانية ربطها بسندات مالية).',
                'code' => 'service_invoice',
                'context' => 'services',
                'company_id' => $company?->id,
                'created_by' => $user?->id,
            ],
            [
                'name' => 'إشعار دائن',
                'description' => 'إشعار دائن (يستخدم لتقليل رصيد العميل أو المورد نتيجة خصم أو إرجاع أو تصحيح مالي).',
                'code' => 'credit_note',
                'context' => 'finance',
                'company_id' => $company?->id,
                'created_by' => $user?->id,
            ],
            [
                'name' => 'إشعار مدين',
                'description' => 'إشعار مدين (يستخدم لزيادة رصيد العميل أو المورد نتيجة إضافة مبلغ أو تصحيح مالي).',
                'code' => 'debit_note',
                'context' => 'finance',
                'company_id' => $company?->id,
                'created_by' => $user?->id,
            ],
            [
                'name' => 'سند قبض',
                'description' => 'سند قبض (يستخدم لتوثيق استلام مبالغ نقدية من العملاء أو جهات أخرى، ويرتبط غالباً بفواتير البيع أو المرتجعات).',
                'code' => 'receipt',
                'context' => 'finance',
                'company_id' => $company?->id,
                'created_by' => $user?->id,
            ],
            [
                'name' => 'سند صرف',
                'description' => 'سند صرف (يستخدم لتوثيق صرف مبالغ نقدية للموردين أو جهات أخرى، ويرتبط غالباً بفواتير الشراء أو المرتجعات).',
                'code' => 'payment',
                'context' => 'finance',
                'company_id' => $company?->id,
                'created_by' => $user?->id,
            ],
            [
                'name' => 'فاتورة خصم',
                'description' => 'فاتورة خصم (تستخدم لتسجيل خصومات أو عروض ترويجية على فواتير البيع، مع تأثير مباشر على الأرباح).',
                'code' => 'discount_invoice',
                'context' => 'sales',
                'company_id' => $company?->id,
                'created_by' => $user?->id,
            ],
        ];
        foreach ($types as $type) {
            InvoiceType::create($type);
        }
    }
}
