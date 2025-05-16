<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\InstallmentPlan;
use App\Http\Requests\InstallmentPlan\StoreInstallmentPlanRequest;
use App\Http\Requests\InstallmentPlan\UpdateInstallmentPlanRequest;
use App\Http\Resources\InstallmentPlan\InstallmentPlanResource;

class InstallmentPlanController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $plans = InstallmentPlan::with(['user', 'invoice', 'installments'])->paginate(20);
        return InstallmentPlanResource::collection($plans);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreInstallmentPlanRequest $request)
    {
        DB::beginTransaction();
        try {
            $plan = InstallmentPlan::create($request->validated());
            $plan->load(['user', 'invoice', 'installments']);
            DB::commit();
            return new InstallmentPlanResource($plan);
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
        $plan = InstallmentPlan::with(['user', 'invoice', 'installments'])->findOrFail($id);
        return new InstallmentPlanResource($plan);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateInstallmentPlanRequest $request, $id)
    {
        $plan = InstallmentPlan::findOrFail($id);
        DB::beginTransaction();
        try {
            $plan->update($request->validated());
            $plan->load(['user', 'invoice', 'installments']);
            DB::commit();
            return new InstallmentPlanResource($plan);
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
        $plan = InstallmentPlan::findOrFail($id);
        DB::beginTransaction();
        try {
            $plan->delete();
            DB::commit();
            return response()->json(['message' => 'Deleted successfully'], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
