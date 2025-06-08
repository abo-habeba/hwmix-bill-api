<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Stock;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Installment;
use App\Models\InvoiceItem;
use Illuminate\Http\Request;
use App\Models\InstallmentPlan;
use Illuminate\Support\Facades\DB;
use App\Http\Resources\Invoice\InvoiceResource;
use App\Http\Requests\Invoice\StoreInvoiceRequest;
use App\Http\Requests\Invoice\UpdateInvoiceRequest;

class InvoiceController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $invoices = Invoice::with(['user', 'invoiceType', 'items', 'installmentPlan'])->paginate(20);
        return InvoiceResource::collection($invoices);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreInvoiceRequest $request)
    {
        DB::beginTransaction();
        try {
            $user = auth()->user();


            $validatedData = $request->validated();
            $companyId = $validatedData['company_id'] = $validatedData['company_id'] ?? $user->company_id;
            $createdBy = $validatedData['created_by'] = $validatedData['created_by'] ?? $user->id;
            $validatedData['status'] = $request->input('status', 'confirmed');


            // إنشاء الفاتورة
            $invoice = Invoice::create([
                'invoice_type_id' => $validatedData['invoice_type_id'],
                'user_id' => $validatedData['user_id'] ?? null,
                'status' => $validatedData['status'] ?? 'confirmed',
                'company_id' => $validatedData['company_id'],
                'created_by' => $validatedData['created_by'],
                'total_amount' => $validatedData['total_amount'],
            ]);

            // إضافة العناصر
            $items = $validatedData['items'] ?? [];
            if (!is_array($items)) {
                throw new \InvalidArgumentException('The items must be an array.');
            }

            foreach ($items as $item) {
                // تحقق من وجود الحقول الأساسية في كل عنصر (يمكن تخصيص الفاليديشن حسب الحاجة)
                if (!isset($item['product_id'], $item['name'], $item['quantity'], $item['unit_price'], $item['discount'], $item['total'])) {
                    throw new \InvalidArgumentException('Missing required item fields.');
                }



                $invoice->items()->create([
                    'product_id' => $item['product_id'],
                    'name' => $item['name'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'discount' => $item['discount'],
                    'total' => $item['total'],
                    'company_id' => $companyId,
                    'created_by' => $createdBy,
                ]);
            }

            // معالجة خطة التقسيط إذا كانت موجودة ونوع الفاتورة مناسب
            if (($validatedData['invoice_type_code'] ?? null) === 'installment_sale' && !empty($validatedData['installment_plan'])) {
                $planData = $validatedData['installment_plan'];

                if (empty($planData['start_date']) || !strtotime($planData['start_date'])) {
                    throw new \InvalidArgumentException('The start_date must be a valid date.');
                }

                if (empty($planData['number_of_installments']) || !is_numeric($planData['number_of_installments']) || $planData['number_of_installments'] <= 0) {
                    throw new \InvalidArgumentException('The number_of_installments must be a positive number.');
                }

                $startDate = Carbon::parse($planData['start_date']);
                $endDate = $startDate->copy()->addMonths($planData['number_of_installments']);

                $planCompanyId = $planData['company_id'] ?? $companyId;
                $planCreatedBy = $planData['created_by'] ?? $createdBy;

                $installmentPlan = InstallmentPlan::create([
                    'invoice_id' => $invoice->id,
                    'customer_id' => $validatedData['user_id'] ?? null,
                    'company_id' => $planCompanyId,
                    'created_by' => $planCreatedBy,
                    'total_amount' => $planData['total_amount'],
                    'down_payment' => $planData['down_payment'],
                    'remaining_amount' => $planData['total_amount'] - $planData['down_payment'],
                    'number_of_installments' => $planData['number_of_installments'],
                    'installment_amount' => $planData['installment_amount'],
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'status' => $planData['status'] ?? 'confirmed',
                ]);

                for ($i = 0; $i < $planData['number_of_installments']; $i++) {
                    $dueDate = $startDate->copy()->addMonthsNoOverflow($i);

                    Installment::create([
                        'installment_plan_id' => $installmentPlan->id,
                        'due_date' => $dueDate,
                        'amount' => $planData['installment_amount'],
                        'status' => $planData['status'] ?? 'confirmed',
                        'remaining' => $planData['installment_amount'],
                        'company_id' => $planCompanyId,
                        'created_by' => $planCreatedBy,
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'message' => trans('messages.invoice_saved_successfully'),
                'invoice' => new InvoiceResource($invoice),
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error occurred while processing invoice: ' . $e->getMessage(), ['exception' => $e]);

            // في بيئة الإنتاج يمكن إعادة رسالة خطأ عامة
            $response = config('app.debug') ? [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ] : [
                'error' => trans('messages.error_processing_request'),
            ];

            return response()->json($response, 500);
        }
    }



    // public function store(StoreInvoiceRequest $request)
    // {
    //     DB::beginTransaction();
    //     try {
    //         $user = auth()->user();
    //         // التحقق من البيانات المدخلة
    //         $validatedData = $request->validated();
    //         $validatedData['company_id'] ??= $user ? $user->company_id : null;
    //         if ($user) {
    //             $validatedData['created_by'] ??= $user->id;
    //         }

    //         // 1. إنشاء الفاتورة
    //         $invoice = Invoice::create([
    //             'invoice_type_id' => $validatedData['invoice_type_id'],
    //             'customer_id' => $validatedData['user_id'] ?? null, // تحقق من وجود user_id
    //             'company_id' => $validatedData['company_id'] ?? ($user ? $user->company_id : null),
    //             'status' => $validatedData['status'] ?? 'confirmed', // القيمة الافتراضية confirmed
    //             'total_amount' => $validatedData['total_amount'],
    //             'created_by' => $validatedData['created_by'] ?? ($user ? $user->id : null),
    //         ]);

    //         // 2. إضافة العناصر
    //         $items = $validatedData['items'] ?? [];
    //         if (!is_array($items)) {
    //             throw new \InvalidArgumentException('The items must be an array.');
    //         }
    //         foreach ($items as $item) {
    //             $invoice->items()->create([
    //                 'product_id' => $item['product_id'],
    //                 'name' => $item['name'],
    //                 'quantity' => $item['quantity'],
    //                 'unit_price' => $item['unit_price'],
    //                 'discount' => $item['discount'],
    //                 'total' => $item['total'],
    //                 'company_id' => $item['company_id'] ?? ($user ? $user->company_id : null),
    //                 'created_by' => $item['created_by'] ?? ($user ? $user->id : null),
    //             ]);
    //         }

    //         // 3. لو نوع الفاتورة تقسيط، ننشئ خطة التقسيط
    //         if (($validatedData['invoice_type_code'] ?? null) === 'installment_sale' && isset($validatedData['installment_plan'])) {
    //             $planData = $validatedData['installment_plan'];
    //             // تحقق من صحة تاريخ البداية
    //             if (!isset($planData['start_date']) || !strtotime($planData['start_date'])) {
    //                 throw new \InvalidArgumentException('The start_date must be a valid date.');
    //             }
    //             $startDate = Carbon::parse($planData['start_date']);
    //             $endDate = $startDate->copy()->addMonths($planData['number_of_installments']);

    //             $installmentPlan = InstallmentPlan::create([
    //                 'invoice_id' => $invoice->id,
    //                 'customer_id' => $validatedData['user_id'] ?? null,
    //                 'company_id' => $planData['company_id'] ?? ($user ? $user->company_id : null),
    //                 'created_by' => $planData['created_by'] ?? ($user ? $user->id : null),
    //                 'total_amount' => $planData['total_amount'],
    //                 'down_payment' => $planData['down_payment'],
    //                 'remaining_amount' => $planData['total_amount'] - $planData['down_payment'],
    //                 'number_of_installments' => $planData['number_of_installments'],
    //                 'installment_amount' => $planData['installment_amount'],
    //                 'start_date' => $startDate,
    //                 'end_date' => $endDate,
    //                 'status' => $planData['status'] ?? 'confirmed', // القيمة الافتراضية confirmed
    //             ]);

    //             // 4. إنشاء جدول الأقساط (installments)
    //             for ($i = 0; $i < $planData['number_of_installments']; $i++) {
    //                 // due_date: نفس يوم start_date في كل شهر
    //                 $dueDate = $startDate->copy()->addMonthsNoOverflow($i);
    //                 Installment::create([
    //                     'installment_plan_id' => $installmentPlan->id,
    //                     'due_date' => $dueDate, // تاريخ القسط: نفس اليوم من كل شهر
    //                     'amount' => $planData['installment_amount'],
    //                     'status' => $planData['status'] ?? 'confirmed', // القيمة الافتراضية confirmed
    //                     'remaining' => $planData['installment_amount'],
    //                     'company_id' => $planData['company_id'] ?? ($user ? $user->company_id : null),
    //                     'created_by' => $planData['created_by'] ?? ($user ? $user->id : null),
    //                 ]);
    //             }
    //         }

    //         DB::commit(); // تأكيد جميع العمليات
    //         return response()->json([
    //             'message' => trans('messages.invoice_saved_successfully'),
    //             'invoice' => new InvoiceResource($invoice)
    //         ], 201);
    //     } catch (\Exception $e) {
    //         DB::rollBack();
    //         \Log::error('Error occurred while processing invoice: ' . $e->getMessage(), ['exception' => $e]);
    //         return response()->json([
    //             'error' => $e->getMessage(),
    //             'trace' => $e->getTraceAsString(),
    //         ], 500);
    //     }
    // }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $invoice = Invoice::with(['user', 'invoiceType', 'items', 'installmentPlan'])->findOrFail($id);
        return new InvoiceResource($invoice);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateInvoiceRequest $request, $id)
    {
        $invoice = Invoice::findOrFail($id);
        DB::beginTransaction();
        try {
            $invoice->update($request->validated());
            $invoice->load(['user', 'invoiceType', 'items', 'installmentPlan']);
            DB::commit();
            return new InvoiceResource($invoice);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $invoice = Invoice::findOrFail($id);
        DB::beginTransaction();
        try {
            $invoice->delete();
            DB::commit();
            return response()->json(['message' => 'Deleted successfully'], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
