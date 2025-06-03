<?php
namespace App\Http\Controllers;

use App\Models\InstallmentPayment;
use App\Http\Requests\InstallmentPayment\StoreInstallmentPaymentRequest;
use App\Http\Requests\InstallmentPayment\UpdateInstallmentPaymentRequest;
use App\Http\Resources\InstallmentPayment\InstallmentPaymentResource;

class InstallmentPaymentController extends Controller
{
    public function index()
    {
        return InstallmentPaymentResource::collection(InstallmentPayment::paginate(20));
    }
    public function store(StoreInstallmentPaymentRequest $request)
    {
        $payment = InstallmentPayment::create($request->validated());
        return new InstallmentPaymentResource($payment);
    }
    public function show($id)
    {
        return new InstallmentPaymentResource(InstallmentPayment::findOrFail($id));
    }
    public function update(UpdateInstallmentPaymentRequest $request, $id)
    {
        $payment = InstallmentPayment::findOrFail($id);
        $payment->update($request->validated());
        return new InstallmentPaymentResource($payment);
    }
    public function destroy($id)
    {
        $payment = InstallmentPayment::findOrFail($id);
        $payment->delete();
        return response()->json(['message' => 'Deleted successfully']);
    }
}
