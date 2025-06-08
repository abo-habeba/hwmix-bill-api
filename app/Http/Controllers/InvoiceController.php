<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Invoice\StoreInvoiceRequest;
use App\Http\Requests\Invoice\UpdateInvoiceRequest;
use App\Http\Resources\Invoice\InvoiceResource;
use App\Models\Installment;
use App\Models\InstallmentPlan;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\Stock;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class InvoiceController extends Controller
{
    protected array $withRelations = [
        'user',
        'invoiceType',
        'items',
        'installmentPlan',
        'company',
        'creator',
    ];

    public function index()
    {
        $invoices = Invoice::with($this->withRelations)->paginate(20);
        return InvoiceResource::collection($invoices);
    }

    public function store(StoreInvoiceRequest $request)
    {
        DB::beginTransaction();
        try {
            $user = Auth::user();

            $validatedData = $request->validated();
            $companyId = $validatedData['company_id'] = $validatedData['company_id'] ?? $user->company_id;
            $createdBy = $validatedData['created_by'] = $validatedData['created_by'] ?? $user->id;
            $validatedData['status'] = $request->input('status', 'confirmed');

            $invoice = Invoice::create([
                'invoice_type_id' => $validatedData['invoice_type_id'],
                'user_id' => $validatedData['user_id'] ?? null,
                'status' => $validatedData['status'] ?? 'confirmed',
                'company_id' => $validatedData['company_id'],
                'created_by' => $validatedData['created_by'],
                'total_amount' => $validatedData['total_amount'],
            ]);

            $items = $validatedData['items'] ?? [];
            if (!is_array($items)) {
                throw new \InvalidArgumentException('The items must be an array.');
            }

            foreach ($items as $item) {
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
                    'company_id' => $validatedData['company_id'],
                    'created_by' => $validatedData['created_by'],
                ]);
            }
            // invoice_type_code: installment_sale
            if (($validatedData['invoice_type_code'] ?? null) === 'installment_sale' && !empty($validatedData['installment_plan'])) {
                $planData = $validatedData['installment_plan'];

                if (empty($planData['start_date']) || !strtotime($planData['start_date'])) {
                    throw new \InvalidArgumentException('The start_date must be a valid date.');
                }

                if (empty($planData['number_of_installments']) || !is_numeric($planData['number_of_installments']) || $planData['number_of_installments'] <= 0) {
                    throw new \InvalidArgumentException('The number_of_installments must be a positive number.');
                }

                $startDate = Carbon::parse($planData['start_date']);
                $numberOfInstallments = (int) $planData['number_of_installments'];  // تحويل لقيمة صحيحة

                $endDate = $startDate->copy()->addMonths($numberOfInstallments);

                $planCompanyId = $planData['company_id'] ?? $companyId;
                $planCreatedBy = $planData['created_by'] ?? $createdBy;

                $installmentPlan = InstallmentPlan::create([
                    'invoice_id' => $invoice->id,
                    'user_id' => $validatedData['user_id'] ?? null,
                    'company_id' => $validatedData['company_id'],
                    'created_by' => $validatedData['created_by'],
                    'total_amount' => $planData['total_amount'],
                    'down_payment' => $planData['down_payment'],
                    'remaining_amount' => $planData['total_amount'] - $planData['down_payment'],
                    'number_of_installments' => $numberOfInstallments,
                    'installment_amount' => $planData['installment_amount'],
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'status' => $planData['status'] ?? 'confirmed',
                ]);

                for ($i = 0; $i < $numberOfInstallments; $i++) {
                    $dueDate = $startDate->copy()->addMonthsNoOverflow($i);

                    Installment::create([
                        'installment_plan_id' => $installmentPlan->id,
                        'due_date' => $dueDate,
                        'installment_number' => $i,
                        'amount' => $planData['installment_amount'],
                        'status' => $planData['status'] ?? 'confirmed',
                        'remaining' => $planData['installment_amount'],
                        'company_id' => $planCompanyId,
                        'created_by' => $planCreatedBy,
                    ]);
                }
            }

            DB::commit();

            $invoice->load($this->withRelations);  // تحميل العلاقات

            return new InvoiceResource($invoice);
        } catch (\Exception $e) {
            DB::rollBack();

            $response = config('app.debug') ? [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ] : [
                'error' => trans('messages.error_processing_request'),
            ];

            return response()->json($response, 500);
        }
    }

    public function show($id)
    {
        $invoice = Invoice::with($this->withRelations)->findOrFail($id);
        return new InvoiceResource($invoice);
    }

    public function update(UpdateInvoiceRequest $request, $id)
    {
        $invoice = Invoice::findOrFail($id);
        DB::beginTransaction();
        try {
            $invoice->update($request->validated());
            $invoice->load($this->withRelations);
            DB::commit();
            return new InvoiceResource($invoice);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

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
