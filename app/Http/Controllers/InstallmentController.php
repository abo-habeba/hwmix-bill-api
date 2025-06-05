<?php

namespace App\Http\Controllers;

use App\Http\Requests\Installment\StoreInstallmentRequest;
use App\Http\Requests\Installment\UpdateInstallmentRequest;
use App\Http\Resources\Installment\InstallmentResource;
use App\Models\Installment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InstallmentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $installments = Installment::with(['installmentPlan'])->paginate(20);
        return InstallmentResource::collection($installments);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreInstallmentRequest $request)
    {
        DB::beginTransaction();
        try {
            $installment = Installment::create($request->validated());
            $installment->load(['installmentPlan', 'payments']);
            DB::commit();
            return new InstallmentResource($installment);
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
        $installment = Installment::with(['installmentPlan', 'payments'])->findOrFail($id);
        return new InstallmentResource($installment);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateInstallmentRequest $request, $id)
    {
        $installment = Installment::findOrFail($id);
        DB::beginTransaction();
        try {
            $installment->update($request->validated());
            $installment->load(['installmentPlan', 'payments']);
            DB::commit();
            return new InstallmentResource($installment);
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
        $installment = Installment::findOrFail($id);
        DB::beginTransaction();
        try {
            $installment->delete();
            DB::commit();
            return response()->json(['message' => 'Deleted successfully'], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
