<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\InvoiceType;
use App\Http\Requests\InvoiceType\StoreInvoiceTypeRequest;
use App\Http\Requests\InvoiceType\UpdateInvoiceTypeRequest;
use App\Http\Resources\InvoiceType\InvoiceTypeResource;

class InvoiceTypeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $types = InvoiceType::paginate(20);
        return InvoiceTypeResource::collection($types);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreInvoiceTypeRequest $request)
    {
        DB::beginTransaction();
        try {
            $type = InvoiceType::create($request->validated());
            DB::commit();
            return new InvoiceTypeResource($type);
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
        $type = InvoiceType::findOrFail($id);
        return new InvoiceTypeResource($type);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateInvoiceTypeRequest $request, $id)
    {
        $type = InvoiceType::findOrFail($id);
        DB::beginTransaction();
        try {
            $type->update($request->validated());
            DB::commit();
            return new InvoiceTypeResource($type);
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
        $type = InvoiceType::findOrFail($id);
        DB::beginTransaction();
        try {
            $type->delete();
            DB::commit();
            return response()->json(['message' => 'Deleted successfully'], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
