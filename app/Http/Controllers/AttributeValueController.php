<?php

namespace App\Http\Controllers;

use App\Models\AttributeValue;
use App\Http\Controllers\Controller;
use Symfony\Component\HttpFoundation\Response;
use App\Http\Resources\AttributeValue\AttributeValueResource;
use App\Http\Requests\AttributeValue\StoreAttributeValueRequest;
use App\Http\Requests\AttributeValue\UpdateAttributeValueRequest;

class AttributeValueController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $attributeValues = AttributeValue::all();
        return AttributeValueResource::collection($attributeValues);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreAttributeValueRequest $request)
    {
        $authUser = auth()->user();
        $validatedData = $request->validated();
        $validatedData['created_by'] = $validatedData['created_by'] ?? $authUser->id;
        $attributeValue = AttributeValue::create($validatedData);
        return new AttributeValueResource($attributeValue);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $attributeValue = AttributeValue::findOrFail($id);
        return new AttributeValueResource($attributeValue);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateAttributeValueRequest $request, string $id)
    {
        $attributeValue = AttributeValue::findOrFail($id);
        $attributeValue->update($request->validated());
        return new AttributeValueResource($attributeValue);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $attributeValue = AttributeValue::findOrFail($id);
        $attributeValue->delete();

        return response()->noContent();
    }
}
