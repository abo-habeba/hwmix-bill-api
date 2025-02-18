<?php

namespace App\Http\Controllers;

use App\Models\Warehouse;
use App\Http\Controllers\Controller;
use Symfony\Component\HttpFoundation\Response;
use App\Http\Resources\Warehouse\WarehouseResource;
use App\Http\Requests\Warehouse\StoreWarehouseRequest;
use App\Http\Requests\Warehouse\UpdateWarehouseRequest;

class WarehouseController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $warehouses = Warehouse::all();
        return WarehouseResource::collection($warehouses);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreWarehouseRequest $request)
    {
        $authUser = auth()->user();
        $validatedData = $request->validated();
        $validatedData['company_id'] = $validatedData['company_id'] ?? $authUser->company_id;
        $validatedData['created_by'] = $validatedData['created_by'] ?? $authUser->id;

        $warehouse = Warehouse::create($validatedData);
        return new WarehouseResource($warehouse);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $warehouse = Warehouse::findOrFail($id);
        return new WarehouseResource($warehouse);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateWarehouseRequest $request, string $id)
    {
        $warehouse = Warehouse::findOrFail($id);
        $warehouse->update($request->validated());
        return new WarehouseResource($warehouse);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $warehouse = Warehouse::findOrFail($id);
        $warehouse->delete();

        return response()->noContent();
    }
}
