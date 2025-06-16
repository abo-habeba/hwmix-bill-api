<?php
namespace App\Http\Controllers;

use App\Http\Requests\InstallmentPayment\PayInstallmentsRequest;
use App\Models\Installment;
use App\Http\Requests\Installment\StoreInstallmentRequest;
use App\Http\Requests\Installment\UpdateInstallmentRequest;
use App\Http\Resources\Installment\InstallmentResource;
use App\Services\InstallmentPaymentService;
use Illuminate\Http\Request;

class InstallmentPaymentController extends Controller
{
    public function index()
    {
        return InstallmentResource::collection(Installment::paginate(20));
    }
    public function store(StoreInstallmentRequest $request)
    {
        $installment = Installment::create($request->validated());
        return new InstallmentResource($installment);
    }
    public function show($id)
    {
        return new InstallmentResource(Installment::findOrFail($id));
    }
    public function update(UpdateInstallmentRequest $request, $id)
    {
        $installment = Installment::findOrFail($id);
        $installment->update($request->validated());
        return new InstallmentResource($installment);
    }
    public function destroy($id)
    {
        $installment = Installment::findOrFail($id);
        $installment->delete();
        return response()->json(['message' => 'Deleted successfully']);
    }
    public function payInstallments(PayInstallmentsRequest $request)
    {
        $validatedData = $request->validated();

        $cashBoxId = $validatedData['cash_box_id'] ?? auth()->user()->cashBoxeDefault?->id;
        if (!$cashBoxId) {
            return response()->json(['message' => 'Default cash box not found for the user'], 400);
        }

        $service = new InstallmentPaymentService();
        $service->payInstallments(
            $validatedData['installment_ids'],
            $validatedData['amount'],
            [
                'user_id' => $validatedData['user_id'],
                'installment_plan_id' => $validatedData['installment_plan_id'],
                'payment_method_id' => $validatedData['payment_method_id'],
                'cash_box_id' => $cashBoxId,
                'notes' => $validatedData['notes'] ?? '',
                'paid_at' => $validatedData['paid_at'] ?? now(),
                'amount' => $validatedData['amount'],
            ]
        );

        $updatedInstallments = Installment::whereIn('id', $validatedData['installment_ids'])->with(['user', 'creator', 'installmentPlan'])->get();

        return response()->json([
            'message' => 'Installments paid successfully',
            'installments' => InstallmentResource::collection($updatedInstallments)
        ]);
    }
}
