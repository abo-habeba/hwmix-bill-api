<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Http\Controllers\Controller;
use Symfony\Component\HttpFoundation\Response;
use App\Http\Resources\Brand\BrandResource;
use App\Http\Requests\Brand\StoreBrandRequest;
use App\Http\Requests\Brand\UpdateBrandRequest;

class BrandController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $brands = Brand::all();
        return BrandResource::collection($brands);
    }

    /**
     * Store a newly created resource in storage.
     */

    public function store(StoreBrandRequest $request)
    {
        $authUser = auth()->user();
        $validatedData = $request->validated();
        $validatedData['company_id'] = $validatedData['company_id'] ?? $authUser->company_id;
        $validatedData['created_by'] = $validatedData['created_by'] ?? $authUser->id;
        $brand = Brand::create($validatedData);
        return new BrandResource($brand);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $brand = Brand::findOrFail($id);
        return new BrandResource($brand);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateBrandRequest $request, string $id)
    {
        $brand = Brand::findOrFail($id);
        $brand->update($request->validated());
        return new BrandResource($brand);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $brand = Brand::findOrFail($id);
        $brand->delete();

        return response()->noContent();
    }
}
