<?php

namespace App\Http\Controllers;

use App\Http\Resources\PurchaseInvoiceResource;
use App\Models\PurchaseInvoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PurchaseInvoiceController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:view all purchase invoices|view own purchase invoices')->only('index', 'show');
        $this->middleware('permission:create purchase invoices')->only('store');
        $this->middleware('permission:edit purchase invoices')->only('update');
        $this->middleware('permission:delete purchase invoices')->only('destroy');
    }

    // عرض قائمة الفواتير
    // public function index()
    // {
    //     $user = Auth::user();

    //     if ($user->can('view all purchase invoices')) {
    //         $invoices = PurchaseInvoice::all();
    //     } else {
    //         $invoices = PurchaseInvoice::where('user_id', $user->id)->get();
    //     }

    //     return PurchaseInvoiceResource::collection($invoices);
    // }

    // عرض فاتورة محددة
    // public function show(PurchaseInvoice $purchaseInvoice)
    // {
    //     $this->authorizeView($purchaseInvoice);

    //     return new PurchaseInvoiceResource($purchaseInvoice);
    // }

    // إنشاء فاتورة جديدة
    // public function store(Request $request)
    // {
    //     $validated = $request->validate([
    //         'supplier_name' => 'required|string|max:255',
    //         'total_amount' => 'required|numeric|min:0',
    //         'details' => 'nullable|array',
    //     ]);

    //     $invoice = PurchaseInvoice::create([
    //         'supplier_name' => $validated['supplier_name'],
    //         'total_amount' => $validated['total_amount'],
    //         'details' => $validated['details'] ?? [],
    //         'user_id' => Auth::id(),
    //     ]);

    //     return new PurchaseInvoiceResource($invoice);
    // }

    // تعديل فاتورة موجودة
    // public function update(Request $request, PurchaseInvoice $purchaseInvoice)
    // {
    //     $this->authorizeEdit($purchaseInvoice);

    //     $validated = $request->validate([
    //         'supplier_name' => 'sometimes|string|max:255',
    //         'total_amount' => 'sometimes|numeric|min:0',
    //         'details' => 'nullable|array',
    //     ]);

    //     $purchaseInvoice->update($validated);

    //     return new PurchaseInvoiceResource($purchaseInvoice);
    // }

    // حذف فاتورة
    // public function destroy(PurchaseInvoice $purchaseInvoice)
    // {
    //     $this->authorizeEdit($purchaseInvoice);

    //     $purchaseInvoice->delete();

    //     return response()->json(['message' => 'Purchase invoice deleted successfully.']);
    // }

    // تحقق من صلاحية العرض
    // private function authorizeView(PurchaseInvoice $purchaseInvoice)
    // {
    //     if (Auth::user()->cannot('view all purchase invoices') && Auth::id() !== $purchaseInvoice->user_id) {
    //         abort(403, 'You do not have permission to view this invoice.');
    //     }
    // }

    // تحقق من صلاحية التعديل
    // private function authorizeEdit(PurchaseInvoice $purchaseInvoice)
    // {
    //     if (Auth::user()->cannot('edit purchase invoices') || Auth::id() !== $purchaseInvoice->user_id) {
    //         abort(403, 'You do not have permission to edit this invoice.');
    //     }
    // }
}
