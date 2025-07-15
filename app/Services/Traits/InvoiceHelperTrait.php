<?php

namespace App\Services\Traits;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\ProductVariant;
use App\Models\Stock;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log; // تم إضافة استيراد لـ Log

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
            unset($data['invoice_number']);
            $invoice = Invoice::create([
                'invoice_type_id'   => $data['invoice_type_id'],
                'invoice_type_code' => $data['invoice_type_code'] ?? null,
                'due_date'          => $data['due_date'] ?? null,
                'status'            => $data['status'] ?? 'confirmed',
                'user_id'           => $data['user_id'],
                'gross_amount'      => $data['gross_amount'],
                'total_discount'    => $data['total_discount'] ?? 0,
                'net_amount'        => $data['net_amount'],
                'paid_amount'       => $data['paid_amount'] ?? 0,
                'remaining_amount'  => $data['remaining_amount'] ?? 0,
                'round_step'        => $data['round_step'] ?? null,
                'company_id'        => $data['company_id'] ?? null,
                'created_by'        => $data['created_by'] ?? null,
            ]);

            return $invoice;
        } catch (\Throwable $e) {
            // هذا السطر سيسجل الخطأ الفعلي الذي يسبب فشل إنشاء الفاتورة
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
                'invoice_type_id'   => $data['invoice_type_id'],
                'invoice_type_code' => $data['invoice_type_code'] ?? null,
                'due_date'          => $data['due_date'] ?? null,
                'status'            => $data['status'] ?? 'confirmed',
                'user_id'           => $data['user_id'],
                'gross_amount'      => $data['gross_amount'],
                'total_discount'    => $data['total_discount'] ?? 0,
                'net_amount'        => $data['net_amount'],
                'paid_amount'       => $data['paid_amount'] ?? 0,
                'remaining_amount'  => $data['remaining_amount'] ?? 0,
                'round_step'        => $data['round_step'] ?? null,
                'company_id'        => $data['company_id'] ?? null,
                'updated_by'        => $data['updated_by'] ?? null,
            ]);

            return $invoice;
        } catch (\Throwable $e) {
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
                    'discount'   => $item['discount'] ?? 0,
                    'total'      => $item['total'],
                    'company_id' => $companyId,
                    'created_by' => $createdBy,
                ]);
            } catch (\Throwable $e) {
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
            $currentItems = $invoice->items->keyBy('id');
            $newItemsCollection = collect($newItemsData);

            // حذف البنود التي لم تعد موجودة
            $itemsToDelete = $currentItems->diffKeys($newItemsCollection->keyBy('id'));
            foreach ($itemsToDelete as $item) {
                $item->delete();
            }

            // تحديث أو إضافة البنود
            foreach ($newItemsCollection as $itemData) {
                if (isset($itemData['id']) && $existingItem = $currentItems->get($itemData['id'])) {
                    // البند موجود: تحديثه
                    $existingItem->update([
                        'product_id' => $itemData['product_id'] ?? null,
                        'variant_id' => $itemData['variant_id'] ?? null,
                        'name'       => $itemData['name'],
                        'quantity'   => $itemData['quantity'],
                        'unit_price' => $itemData['unit_price'],
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
                        'discount'   => $itemData['discount'] ?? 0,
                        'total'      => $itemData['total'],
                        'company_id' => $companyId,
                        'created_by' => $updatedBy,
                    ]);
                }
            }
        } catch (\Throwable $e) {
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
            InvoiceItem::where('invoice_id', $invoice->id)->delete();
        } catch (\Throwable $e) {
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
                $variant = $variantId ? ProductVariant::find($variantId) : null;

                if (!$variant) {
                    throw ValidationException::withMessages([
                        "items.$index.variant_id" => ["المتغير المختار غير موجود (ID: $variantId)"],
                    ]);
                }

                $totalAvailableQuantity = $variant->stocks()->where('status', 'available')->sum('quantity');
                if ($mode === 'deduct' && $totalAvailableQuantity < $item['quantity']) {
                    throw ValidationException::withMessages([
                        "items.$index.quantity" => ['الكمية غير متوفرة في المخزون.'],
                    ]);
                }
            }
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
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
                $variant = ProductVariant::find($item['variant_id'] ?? null);
                if (!$variant) continue;

                $remaining = $item['quantity'];
                $stocks = $variant->stocks()->where('status', 'available')->orderBy('created_at', 'asc')->get();

                foreach ($stocks as $stock) {
                    if ($remaining <= 0) break;

                    $deduct = min($remaining, $stock->quantity);
                    if ($deduct > 0) {
                        $stock->decrement('quantity', $deduct);
                        $remaining -= $deduct;
                    }
                }
            }
        } catch (\Throwable $e) {
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
            foreach ($invoice->items as $item) {
                $variant = ProductVariant::find($item->variant_id ?? null);
                if (!$variant) continue;

                $remaining = $item->quantity;

                // نبحث عن أحدث مخزون متاح لإعادة الكمية إليه
                $stock = $variant->stocks()->where('status', 'available')->orderBy('created_at', 'desc')->first();

                if ($stock) {
                    $stock->increment('quantity', $remaining);
                } else {
                    // إذا لم يكن هناك مخزون متاح، قد تحتاج لإنشاء سجل مخزون جديد
                    Stock::create([
                        'variant_id' => $variant->id,
                        'quantity'   => $remaining,
                        'status'     => 'available',
                        'company_id' => $invoice->company_id,
                        'created_by' => $invoice->updated_by ?? $invoice->created_by,
                    ]);
                }
            }
        } catch (\Throwable $e) {
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
                $variant = ProductVariant::find($item['variant_id'] ?? null);
                if (!$variant) continue;

                // نبحث عن أحدث مخزون متاح لإضافة الكمية إليه
                $stock = $variant->stocks()->where('status', 'available')->orderBy('created_at', 'desc')->first();

                if ($stock) {
                    $stock->increment('quantity', $item['quantity']);
                } else {
                    // إذا لم يكن هناك مخزون متاح، نقوم بإنشاء سجل مخزون جديد
                    Stock::create([
                        'variant_id' => $item['variant_id'],
                        'quantity'   => $item['quantity'],
                        'status'     => 'available',
                        'company_id' => $companyId,
                        'created_by' => $createdBy,
                    ]);
                }
            }
        } catch (\Throwable $e) {
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
            foreach ($invoice->items as $item) {
                $variant = ProductVariant::find($item->variant_id ?? null);
                if (!$variant) continue;

                $remainingToDeduct = $item->quantity;

                // نبحث عن المخزون المتاح لخصم الكمية منه (من الأقدم للأحدث أو حسب سياسة FIFO/LIFO)
                // هنا نستخدم الأحدث لتبسيط المثال، ولكن قد تحتاج إلى منطق أكثر تعقيدًا
                $stocks = $variant->stocks()->where('status', 'available')->orderBy('created_at', 'desc')->get();

                foreach ($stocks as $stock) {
                    if ($remainingToDeduct <= 0) break;

                    $deduct = min($remainingToDeduct, $stock->quantity);
                    if ($deduct > 0) {
                        $stock->decrement('quantity', $deduct);
                        $remainingToDeduct -= $deduct;
                    }
                }

                // إذا لم يتم خصم كل الكمية (وهو ما يشير إلى نقص في المخزون)، يمكن رمي استثناء أو تسجيل خطأ
                if ($remainingToDeduct > 0) {
                    throw new \Exception("فشل خصم كامل الكمية للمتغير ID: {$variant->id}. الكمية المتبقية للخصم: {$remainingToDeduct}");
                }
            }
        } catch (\Throwable $e) {
            throw $e;
        }
    }
}
