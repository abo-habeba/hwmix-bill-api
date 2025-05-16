<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\InvoiceItem;
use App\Http\Requests\InvoiceItem\StoreInvoiceItemRequest;
use App\Http\Requests\InvoiceItem\UpdateInvoiceItemRequest;
use App\Http\Resources\InvoiceItem\InvoiceItemResource;

class InvoiceItemController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $items = InvoiceItem::with('invoice')->paginate(20);
        return InvoiceItemResource::collection($items);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreInvoiceItemRequest $request)
    {
        DB::beginTransaction();
        try {
            $item = InvoiceItem::create($request->validated());
            $item->load('invoice');
            DB::commit();
            return new InvoiceItemResource($item);
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
        $item = InvoiceItem::with('invoice')->findOrFail($id);
        return new InvoiceItemResource($item);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateInvoiceItemRequest $request, $id)
    {
        $item = InvoiceItem::findOrFail($id);
        DB::beginTransaction();
        try {
            $item->update($request->validated());
            $item->load('invoice');
            DB::commit();
            return new InvoiceItemResource($item);
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
        $item = InvoiceItem::findOrFail($id);
        DB::beginTransaction();
        try {
            $item->delete();
            DB::commit();
            return response()->json(['message' => 'Deleted successfully'], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
