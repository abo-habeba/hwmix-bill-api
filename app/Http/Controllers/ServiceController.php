<?php
namespace App\Http\Controllers;

use App\Models\Service;
use App\Http\Resources\Service\ServiceResource;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    public function index()
    {
        return ServiceResource::collection(Service::paginate(20));
    }
    public function store(Request $request)
    {
        $service = Service::create($request->only(['name', 'description', 'default_price']));
        return new ServiceResource($service);
    }
    public function show($id)
    {
        return new ServiceResource(Service::findOrFail($id));
    }
    public function update(Request $request, $id)
    {
        $service = Service::findOrFail($id);
        $service->update($request->only(['name', 'description', 'default_price']));
        return new ServiceResource($service);
    }
    public function destroy($id)
    {
        $service = Service::findOrFail($id);
        $service->delete();
        return response()->json(['message' => 'Deleted successfully']);
    }
}
