<?php

namespace App\Services\Traits;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\ProductVariant;
use App\Models\Stock;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;

trait InvoiceHelperTrait
{
    /**
     * إنشاء فاتورة جديدة.
     *
     * @param array $data بيانات الفاتورة.
     * @return Invoice الفاتورة التي تم إنشاؤها.
     * @throws \Throwable
     */
    protected function createInvoice(array $data): Invoice
    {
        try {
            // invoice_number يتم إنشاؤه في Model booted method، لذا لا نرسله هنا
            // unset($data['invoice_number']); // لا حاجة لـ unset إذا لم يتم إرساله من الريكويست

            $invoice = Invoice::create([
                'invoice_type_id'   => $data['invoice_type_id'],
                'invoice_type_code' => $data['invoice_type_code'] ?? null,
                'due_date'          => $data['due_date'] ?? null,
                'status'            => $data['status'] ?? 'confirmed', // الحالة ستأتي محسوبة من الخدمة المالية
                'user_id'           => $data['user_id'],
                'gross_amount'      => $data['gross_amount'],
                'total_discount'    => $data['total_discount'] ?? 0,
                'net_amount'        => $data['net_amount'],
                'paid_amount'       => $data['paid_amount'] ?? 0,
                'remaining_amount'  => $data['remaining_amount'] ?? 0,
                'estimated_profit'  => $data['estimated_profit'] ?? 0, // إضافة حقل الربح التقديري
                'round_step'        => $data['round_step'] ?? null,
                'company_id'        => $data['company_id'] ?? null,
                'created_by'        => $data['created_by'] ?? null,
                'notes'             => $data['notes'] ?? null, // إضافة حقل الملاحظات
                'cash_box_id'       => $data['cash_box_id'] ?? null, // إضافة حقل الصندوق النقدي
            ]);

            return $invoice;
        } catch (\Throwable $e) {
            Log::error('InvoiceHelperTrait: فشل في إنشاء الفاتورة.', ['error' => $e->getMessage(), 'data' => $data, 'trace' => $e->getTraceAsString()]);
            throw $e;
        }
    }

    /**
     * تحديث بيانات فاتورة موجودة.
     *
     * @param Invoice $invoice الفاتورة المراد تحديثها.
     * @param array $data البيانات الجديدة للفاتورة.
     * @return Invoice الفاتورة المحدثة.
     * @throws \Throwable
     */
    protected function updateInvoice(Invoice $invoice, array $data): Invoice
    {
        try {
            $invoice->update([
                'invoice_type_id'   => $data['invoice_type_id'] ?? $invoice->invoice_type_id,
                'invoice_type_code' => $data['invoice_type_code'] ?? $invoice->invoice_type_code,
                'due_date'          => $data['due_date'] ?? $invoice->due_date,
                'status'            => $data['status'] ?? $invoice->status, // الحالة ستأتي محسوبة من الخدمة المالية
                'user_id'           => $data['user_id'] ?? $invoice->user_id,
                'gross_amount'      => $data['gross_amount'] ?? $invoice->gross_amount,
                'total_discount'    => $data['total_discount'] ?? $invoice->total_discount,
                'net_amount'        => $data['net_amount'] ?? $invoice->net_amount,
                'paid_amount'       => $data['paid_amount'] ?? $invoice->paid_amount,
                'remaining_amount'  => $data['remaining_amount'] ?? $invoice->remaining_amount,
                'estimated_profit'  => $data['estimated_profit'] ?? $invoice->estimated_profit, // إضافة حقل الربح التقديري
                'round_step'        => $data['round_step'] ?? $invoice->round_step,
                'company_id'        => $data['company_id'] ?? $invoice->company_id,
                'updated_by'        => $data['updated_by'] ?? null,
                'notes'             => $data['notes'] ?? $invoice->notes, // إضافة حقل الملاحظات
                'cash_box_id'       => $data['cash_box_id'] ?? $invoice->cash_box_id, // إضافة حقل الصندوق النقدي
            ]);

            return $invoice;
        } catch (\Throwable $e) {
            Log::error('InvoiceHelperTrait: فشل في تحديث الفاتورة.', ['error' => $e->getMessage(), 'invoice_id' => $invoice->id, 'data' => $data, 'trace' => $e->getTraceAsString()]);
            throw $e;
        }
    }

    /**
     * إنشاء بنود فاتورة جديدة.
     *
     * @param Invoice $invoice الفاتورة المرتبطة بالبنود.
     * @param array $items بيانات البنود.
     * @param int|null $companyId معرف الشركة.
     * @param int|null $createdBy معرف المستخدم المنشئ.
     * @throws \Throwable
     */
    protected function createInvoiceItems(Invoice $invoice, array $items, $companyId = null, $createdBy = null): void
    {
        foreach ($items as $item) {
            try {
                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'product_id' => $item['product_id'] ?? null,
                    'variant_id' => $item['variant_id'] ?? null,
                    'name'       => $item['name'],
                    'quantity'   => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'cost_price' => $item['cost_price'] ?? 0, // إضافة سعر التكلفة
                    'discount'   => $item['discount'] ?? 0,
                    'total'      => $item['total'],
                    'company_id' => $companyId,
                    'created_by' => $createdBy,
                ]);
            } catch (\Throwable $e) {
                Log::error('InvoiceHelperTrait: فشل في إنشاء بند فاتورة.', ['error' => $e->getMessage(), 'invoice_id' => $invoice->id, 'item_data' => $item, 'trace' => $e->getTraceAsString()]);
                throw $e;
            }
        }
    }

    /**
     * مزامنة (تحديث/إضافة/حذف) بنود الفاتورة.
     *
     * @param Invoice $invoice الفاتورة المرتبطة بالبنود.
     * @param array $newItemsData بيانات البنود الجديدة.
     * @param int|null $companyId معرف الشركة.
     * @param int|null $updatedBy معرف المستخدم الذي قام بالتحديث.
     * @throws \Throwable
     */
    protected function syncInvoiceItems(Invoice $invoice, array $newItemsData, $companyId = null, $updatedBy = null): void
    {
        try {
            $currentItems = $invoice->items()->withTrashed()->get()->keyBy('id'); // جلب جميع البنود بما في ذلك المحذوفة ناعماً
            $newItemsCollection = collect($newItemsData);

            // حذف البنود التي لم تعد موجودة في البيانات الجديدة (soft delete)
            $itemsToDelete = $currentItems->diffKeys($newItemsCollection->keyBy('id'));
            foreach ($itemsToDelete as $item) {
                $item->delete(); // يقوم بـ soft delete
            }

            // تحديث أو إضافة البنود
            foreach ($newItemsCollection as $itemData) {
                if (isset($itemData['id']) && $existingItem = $currentItems->get($itemData['id'])) {
                    // البند موجود: تحديثه (وإعادته إذا كان محذوفاً ناعماً)
                    if ($existingItem->trashed()) {
                        $existingItem->restore(); // استعادة البند إذا كان محذوفاً ناعماً
                    }
                    $existingItem->update([
                        'product_id' => $itemData['product_id'] ?? null,
                        'variant_id' => $itemData['variant_id'] ?? null,
                        'name'       => $itemData['name'],
                        'quantity'   => $itemData['quantity'],
                        'unit_price' => $itemData['unit_price'],
                        'cost_price' => $itemData['cost_price'] ?? 0, // إضافة سعر التكلفة
                        'discount'   => $itemData['discount'] ?? 0,
                        'total'      => $itemData['total'],
                        'company_id' => $companyId,
                        'updated_by' => $updatedBy,
                    ]);
                } else {
                    // البند جديد: إنشاؤه
                    InvoiceItem::create([
                        'invoice_id' => $invoice->id,
                        'product_id' => $itemData['product_id'] ?? null,
                        'variant_id' => $itemData['variant_id'] ?? null,
                        'name'       => $itemData['name'],
                        'quantity'   => $itemData['quantity'],
                        'unit_price' => $itemData['unit_price'],
                        'cost_price' => $itemData['cost_price'] ?? 0, // إضافة سعر التكلفة
                        'discount'   => $itemData['discount'] ?? 0,
                        'total'      => $itemData['total'],
                        'company_id' => $companyId,
                        'created_by' => $updatedBy, // المنشئ هو من قام بالتحديث
                    ]);
                }
            }
        } catch (\Throwable $e) {
            Log::error('InvoiceHelperTrait: فشل في مزامنة بنود الفاتورة.', ['error' => $e->getMessage(), 'invoice_id' => $invoice->id, 'new_items_data' => $newItemsData, 'trace' => $e->getTraceAsString()]);
            throw $e;
        }
    }

    /**
     * حذف بنود الفاتورة.
     *
     * @param Invoice $invoice الفاتورة المراد حذف بنودها.
     * @throws \Throwable
     */
    protected function deleteInvoiceItems(Invoice $invoice): void
    {
        try {
            // استخدام soft delete
            $invoice->items()->delete();
        } catch (\Throwable $e) {
            Log::error('InvoiceHelperTrait: فشل في حذف بنود الفاتورة.', ['error' => $e->getMessage(), 'invoice_id' => $invoice->id, 'trace' => $e->getTraceAsString()]);
            throw $e;
        }
    }

    /**
     * التحقق من توفر مخزون المتغيرات.
     *
     * @param array $items بنود الفاتورة للتحقق.
     * @param string $mode وضع التحقق ('deduct' للخصم، 'none' للتجاهل).
     * @throws ValidationException إذا كانت الكمية غير متوفرة.
     * @throws \Throwable
     */
    protected function checkVariantsStock(array $items, string $mode = 'deduct'): void
    {
        try {
            if ($mode === 'none') return;

            foreach ($items as $index => $item) {
                $variantId = $item['variant_id'] ?? null;
                $quantityRequested = $item['quantity'] ?? 0;

                if (!$variantId) {
                    // إذا لم يكن هناك variant_id، قد يكون هذا بند خدمة أو نصي لا يتطلب مخزونًا
                    continue;
                }

                $variant = ProductVariant::find($variantId);

                if (!$variant) {
                    throw ValidationException::withMessages([
                        "items.$index.variant_id" => ["المتغير المختار غير موجود (ID: $variantId)"],
                    ]);
                }

                $totalAvailableQuantity = $variant->stocks()->where('status', 'available')->sum('quantity');
                if ($mode === 'deduct' && $totalAvailableQuantity < $quantityRequested) {
                    throw ValidationException::withMessages([
                        "items.$index.quantity" => ['الكمية المطلوبة غير متوفرة في المخزون. الكمية المتاحة: ' . $totalAvailableQuantity],
                    ]);
                }
            }
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('InvoiceHelperTrait: فشل في التحقق من مخزون المتغيرات.', ['error' => $e->getMessage(), 'items' => $items, 'trace' => $e->getTraceAsString()]);
            throw $e;
        }
    }

    /**
     * خصم الكمية من المخزون لبنود الفاتورة.
     *
     * @param array $items بنود الفاتورة لخصم المخزون.
     * @throws \Throwable
     */
    protected function deductStockForItems(array $items): void
    {
        try {
            foreach ($items as $item) {
                $variantId = $item['variant_id'] ?? null;
                $quantityToDeduct = $item['quantity'] ?? 0;

                if (!$variantId || $quantityToDeduct <= 0) continue;

                $variant = ProductVariant::find($variantId);
                if (!$variant) continue;

                $remaining = $quantityToDeduct;
                $stocks = $variant->stocks()->where('status', 'available')->orderBy('created_at', 'asc')->get();

                foreach ($stocks as $stock) {
                    if ($remaining <= 0) break;

                    $deduct = min($remaining, $stock->quantity);
                    if ($deduct > 0) {
                        $stock->decrement('quantity', $deduct);
                        $remaining -= $deduct;
                    }
                }

                if ($remaining > 0) {
                    Log::warning('InvoiceHelperTrait: لم يتم خصم كامل الكمية للمتغير.', ['variant_id' => $variantId, 'remaining_to_deduct' => $remaining]);
                    // يمكن رمي استثناء هنا إذا كان عدم خصم كامل الكمية يعتبر خطأً فادحًا
                }
            }
        } catch (\Throwable $e) {
            Log::error('InvoiceHelperTrait: فشل في خصم المخزون لبنود الفاتورة.', ['error' => $e->getMessage(), 'items' => $items, 'trace' => $e->getTraceAsString()]);
            throw $e;
        }
    }

    /**
     * إعادة الكمية إلى المخزون لبنود الفاتورة (تستخدم عادة في إلغاء فاتورة بيع).
     *
     * @param Invoice $invoice الفاتورة المراد إعادة مخزون بنودها.
     * @throws \Throwable
     */
    protected function returnStockForItems(Invoice $invoice): void
    {
        try {
            // يجب تحميل بنود الفاتورة بما في ذلك المحذوفة ناعماً لضمان إعادة كل المخزون
            $invoice->load('itemsWithTrashed');
            foreach ($invoice->itemsWithTrashed as $item) {
                $variantId = $item->variant_id ?? null;
                $quantityToReturn = $item->quantity ?? 0;

                if (!$variantId || $quantityToReturn <= 0) continue;

                $variant = ProductVariant::find($variantId);
                if (!$variant) continue;

                // نبحث عن أحدث مخزون متاح لإعادة الكمية إليه
                $stock = $variant->stocks()->where('status', 'available')->orderBy('created_at', 'desc')->first();

                if ($stock) {
                    $stock->increment('quantity', $quantityToReturn);
                } else {
                    // إذا لم يكن هناك مخزون متاح، قد تحتاج لإنشاء سجل مخزون جديد
                    Stock::create([
                        'variant_id' => $variant->id,
                        'quantity'   => $quantityToReturn,
                        'status'     => 'available',
                        'company_id' => $invoice->company_id,
                        'created_by' => $invoice->updated_by ?? $invoice->created_by,
                    ]);
                }
            }
        } catch (\Throwable $e) {
            Log::error('InvoiceHelperTrait: فشل في إعادة المخزون لبنود الفاتورة.', ['error' => $e->getMessage(), 'invoice_id' => $invoice->id, 'trace' => $e->getTraceAsString()]);
            throw $e;
        }
    }

    /**
     * زيادة الكمية في المخزون لبنود الفاتورة (تستخدم عادة في إنشاء/تحديث فاتورة شراء).
     *
     * @param array $items بنود الفاتورة لزيادة المخزون.
     * @param int|null $companyId معرف الشركة.
     * @param int|null $createdBy معرف المستخدم المنشئ.
     * @throws \Throwable
     */
    protected function incrementStockForItems(array $items, ?int $companyId = null, ?int $createdBy = null): void
    {
        try {
            foreach ($items as $item) {
                $variantId = $item['variant_id'] ?? null;
                $quantityToIncrement = $item['quantity'] ?? 0;

                if (!$variantId || $quantityToIncrement <= 0) continue;

                $variant = ProductVariant::find($variantId);
                if (!$variant) continue;

                // نبحث عن أحدث مخزون متاح لإضافة الكمية إليه
                $stock = $variant->stocks()->where('status', 'available')->orderBy('created_at', 'desc')->first();

                if ($stock) {
                    $stock->increment('quantity', $quantityToIncrement);
                } else {
                    // إذا لم يكن هناك مخزون متاح، نقوم بإنشاء سجل مخزون جديد
                    Stock::create([
                        'variant_id' => $variantId,
                        'quantity'   => $quantityToIncrement,
                        'status'     => 'available',
                        'company_id' => $companyId,
                        'created_by' => $createdBy,
                    ]);
                }
            }
        } catch (\Throwable $e) {
            Log::error('InvoiceHelperTrait: فشل في زيادة المخزون لبنود الفاتورة.', ['error' => $e->getMessage(), 'items' => $items, 'trace' => $e->getTraceAsString()]);
            throw $e;
        }
    }

    /**
     * خصم الكمية من المخزون لبنود الفاتورة (تستخدم عادة في إلغاء/تحديث فاتورة شراء).
     *
     * @param Invoice $invoice الفاتورة المراد خصم مخزون بنودها.
     * @throws \Throwable
     */
    protected function decrementStockForInvoiceItems(Invoice $invoice): void
    {
        try {
            // يجب تحميل بنود الفاتورة بما في ذلك المحذوفة ناعماً لضمان خصم كل المخزون
            $invoice->load('itemsWithTrashed');
            foreach ($invoice->itemsWithTrashed as $item) {
                $variantId = $item->variant_id ?? null;
                $quantityToDeduct = $item->quantity ?? 0;

                if (!$variantId || $quantityToDeduct <= 0) continue;

                $variant = ProductVariant::find($variantId);
                if (!$variant) continue;

                $remainingToDeduct = $quantityToDeduct;

                // نبحث عن المخزون المتاح لخصم الكمية منه (من الأقدم للأحدث أو حسب سياسة FIFO/LIFO)
                $stocks = $variant->stocks()->where('status', 'available')->orderBy('created_at', 'desc')->get();

                foreach ($stocks as $stock) {
                    if ($remainingToDeduct <= 0) break;

                    $deduct = min($remainingToDeduct, $stock->quantity);
                    if ($deduct > 0) {
                        $stock->decrement('quantity', $deduct);
                        $remainingToDeduct -= $deduct;
                    }
                }

                if ($remainingToDeduct > 0) {
                    Log::warning('InvoiceHelperTrait: فشل خصم كامل الكمية للمتغير عند التخفيض.', ['variant_id' => $variantId, 'remaining_to_deduct' => $remainingToDeduct]);
                    // يمكن رمي استثناء هنا إذا كان عدم خصم كامل الكمية يعتبر خطأً فادحًا
                    // throw new \Exception("فشل خصم كامل الكمية للمتغير ID: {$variant->id}. الكمية المتبقية للخصم: {$remainingToDeduct}");
                }
            }
        } catch (\Throwable $e) {
            Log::error('InvoiceHelperTrait: فشل في خصم المخزون لبنود الفاتورة (decrement).', ['error' => $e->getMessage(), 'invoice_id' => $invoice->id, 'trace' => $e->getTraceAsString()]);
            throw $e;
        }
    }
}
