<?php

namespace App\Http\Controllers;

use App\Models\Attribute;
use App\Http\Controllers\Controller;
use Symfony\Component\HttpFoundation\Response;
use App\Http\Resources\Attribute\AttributeResource;
use App\Http\Requests\Attribute\StoreAttributeRequest;
use App\Http\Requests\Attribute\UpdateAttributeRequest;


class AttributeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $attributes = Attribute::with('values')->get();
        return AttributeResource::collection($attributes);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreAttributeRequest $request)
    {
        $authUser = auth()->user();
        $validatedData = $request->validated();
        $validatedData['company_id'] = $validatedData['company_id'] ?? $authUser->company_id;
        $validatedData['created_by'] = $validatedData['created_by'] ?? $authUser->id;


        $attribute = Attribute::create($validatedData);
        return new AttributeResource($attribute);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $attribute = Attribute::findOrFail($id);
        return new AttributeResource($attribute);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateAttributeRequest $request, string $id)
    {
        $attribute = Attribute::findOrFail($id);
        $attribute->update($request->validated());
        return new AttributeResource($attribute);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $attribute = Attribute::findOrFail($id);
        $attribute->delete();

        return response()->noContent();
    }
}
