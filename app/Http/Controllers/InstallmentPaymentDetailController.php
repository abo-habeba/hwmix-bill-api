<?php

namespace App\Http\Controllers;

use App\Models\InstallmentPaymentDetail;
use App\Http\Requests\InstallmentPaymentDetail\StoreInstallmentPaymentDetailRequest;
use App\Http\Requests\InstallmentPaymentDetail\UpdateInstallmentPaymentDetailRequest;
use App\Http\Resources\InstallmentPaymentDetail\InstallmentPaymentDetailResource;

class InstallmentPaymentDetailController extends Controller
{
    public function index()
    {
        return InstallmentPaymentDetailResource::collection(InstallmentPaymentDetail::all());
    }

    public function store(StoreInstallmentPaymentDetailRequest $request)
    {
        $detail = InstallmentPaymentDetail::create($request->validated());
        return new InstallmentPaymentDetailResource($detail);
    }

    public function show(InstallmentPaymentDetail $installmentPaymentDetail)
    {
        return new InstallmentPaymentDetailResource($installmentPaymentDetail);
    }

    public function update(UpdateInstallmentPaymentDetailRequest $request, InstallmentPaymentDetail $installmentPaymentDetail)
    {
        $installmentPaymentDetail->update($request->validated());
        return new InstallmentPaymentDetailResource($installmentPaymentDetail);
    }

    public function destroy(InstallmentPaymentDetail $installmentPaymentDetail)
    {
        $installmentPaymentDetail->delete();
        return response()->json(['message' => 'Deleted successfully']);
    }
}
