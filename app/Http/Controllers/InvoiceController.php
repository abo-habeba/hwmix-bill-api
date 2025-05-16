<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\Stock;
use App\Http\Requests\Invoice\StoreInvoiceRequest;
use App\Http\Requests\Invoice\UpdateInvoiceRequest;
use App\Http\Resources\Invoice\InvoiceResource;

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
            $invoice = Invoice::create($request->validated());
            // إضافة عناصر الفاتورة وتحديث المخزون
            $items = $request->input('items', []);
            foreach ($items as $itemData) {
                $invoiceItem = new InvoiceItem($itemData);
                $invoiceItem->invoice_id = $invoice->id;
                $invoiceItem->save();
                // تحديث المخزون إذا كان مرتبط بمنتج
                if (!empty($invoiceItem->product_id)) {
                    $product = Product::find($invoiceItem->product_id);
                    if ($product && $product->stock) {
                        $product->stock->decrement('quantity', $invoiceItem->quantity);
                    }
                }
            }
            $invoice->load(['user', 'invoiceType', 'items', 'installmentPlan']);
            DB::commit();
            return new InvoiceResource($invoice);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
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
