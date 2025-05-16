<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Payment;
use App\Http\Requests\Payment\StorePaymentRequest;
use App\Http\Requests\Payment\UpdatePaymentRequest;
use App\Http\Resources\Payment\PaymentResource;

class PaymentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $payments = Payment::with(['user', 'installments'])->paginate(20);
        return PaymentResource::collection($payments);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StorePaymentRequest $request)
    {
        DB::beginTransaction();
        try {
            $payment = Payment::create($request->validated());
            $payment->load(['user', 'installments']);
            DB::commit();
            return new PaymentResource($payment);
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
        $payment = Payment::with(['user', 'installments'])->findOrFail($id);
        return new PaymentResource($payment);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdatePaymentRequest $request, $id)
    {
        $payment = Payment::findOrFail($id);
        DB::beginTransaction();
        try {
            $payment->update($request->validated());
            $payment->load(['user', 'installments']);
            DB::commit();
            return new PaymentResource($payment);
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
        $payment = Payment::findOrFail($id);
        DB::beginTransaction();
        try {
            $payment->delete();
            DB::commit();
            return response()->json(['message' => 'Deleted successfully'], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
