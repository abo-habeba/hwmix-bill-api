<?php

namespace App\Http\Controllers;

use App\Http\Requests\Invoice\StoreInvoiceRequest;
use App\Http\Requests\Invoice\UpdateInvoiceRequest;
use App\Http\Resources\Invoice\InvoiceResource;
use App\Models\Invoice;
use App\Models\InvoiceType;
use App\Services\ServiceResolver;  // إضافة استيراد الكلاس ServiceResolver
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InvoiceController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $invoices = Invoice::with(['user','company', 'invoiceType', 'items', 'installmentPlan'])->paginate(20);
        return InvoiceResource::collection($invoices);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreInvoiceRequest $request)
    {
        try {
            // التحقق المبدئي
            $validated = $request->validated();

            // استخراج نوع المستند
            $invoiceType = InvoiceType::findOrFail($validated['invoice_type_id']);
            $invoiceTypeCode = $validated['invoice_type_code'] ?? $invoiceType->code;

            // اختيار الخدمة المناسبة
            $serviceResolver = new ServiceResolver();  // إنشاء كائن من الكلاس ServiceResolver
            $service = $serviceResolver->resolve($invoiceTypeCode);  // استدعاء الطريقة على الكائن

            // تنفيذ الخدمة داخل معاملة قاعدة البيانات
            $responseDTO = DB::transaction(function () use ($service, $validated) {
                return $service->create($validated);
            });

            // تم نقل منطق الأقساط إلى InvoiceCreationService

            // إرجاع استجابة النجاح
            return response()->json([
                'status' => 'success',
                'message' => 'تم إنشاء المستند بنجاح',
                'data' => [
                    'invoice' => $responseDTO,
                    // 'invoice' => new InvoiceResource($responseDTO),
                ],
            ], 201);
        } catch (ValidationException $e) {
            // إرجاع استجابة خطأ تحقق داخلي
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Exception $e) {
            // إرجاع استجابة خطأ غير متوقع مع تفاصيل الخطأ
            return response()->json([
                'status' => 'error',
                'message' => 'حدث خطأ أثناء إنشاء المستند',
                'error' => [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                ],
            ], 500);
        }
    }

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
