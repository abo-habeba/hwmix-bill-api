<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Attribute\StoreAttributeRequest;
use App\Http\Requests\Attribute\UpdateAttributeRequest;
use App\Http\Resources\Attribute\AttributeResource;
use App\Models\Attribute;
use Illuminate\Support\Facades\DB;

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
        DB::beginTransaction();
        $attribute = Attribute::find($request->attribute_id);
        try {
            $authUser = auth()->user();
            $validatedData = $request->validated();
            $validatedData['company_id'] = $validatedData['company_id'] ?? $authUser->company_id;
            $validatedData['created_by'] = $validatedData['created_by'] ?? $authUser->id;

            if (!$attribute) {
                $attribute = Attribute::create(
                    [
                        'name' => $validatedData['name'],
                        'company_id' => $validatedData['company_id'],
                        'created_by' => $validatedData['created_by'],
                    ]
                );
            }

            if (!empty($validatedData['name_value'])) {
                $attribute->values()->create([
                    'name' => $validatedData['name_value'],
                    'value' => $validatedData['value'],
                    'created_by' => $validatedData['created_by'],
                ]);
            }

            DB::commit();

            return new AttributeResource($attribute);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'حدث خطأ غير متوقع أثناء حفظ الخاصية. برجاء المحاولة لاحقًا.',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ], 500);
        }
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
