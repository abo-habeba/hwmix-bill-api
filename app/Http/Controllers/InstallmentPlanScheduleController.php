<?php
namespace App\Http\Controllers;

use App\Models\InstallmentPlanSchedule;
use App\Http\Requests\InstallmentPlanSchedule\StoreInstallmentPlanScheduleRequest;
use App\Http\Requests\InstallmentPlanSchedule\UpdateInstallmentPlanScheduleRequest;
use App\Http\Resources\InstallmentPlanSchedule\InstallmentPlanScheduleResource;

class InstallmentPlanScheduleController extends Controller
{
    public function index()
    {
        return InstallmentPlanScheduleResource::collection(InstallmentPlanSchedule::paginate(20));
    }
    public function store(StoreInstallmentPlanScheduleRequest $request)
    {
        $schedule = InstallmentPlanSchedule::create($request->validated());
        return new InstallmentPlanScheduleResource($schedule);
    }
    public function show($id)
    {
        return new InstallmentPlanScheduleResource(InstallmentPlanSchedule::findOrFail($id));
    }
    public function update(UpdateInstallmentPlanScheduleRequest $request, $id)
    {
        $schedule = InstallmentPlanSchedule::findOrFail($id);
        $schedule->update($request->validated());
        return new InstallmentPlanScheduleResource($schedule);
    }
    public function destroy($id)
    {
        $schedule = InstallmentPlanSchedule::findOrFail($id);
        $schedule->delete();
        return response()->json(['message' => 'Deleted successfully']);
    }
}
