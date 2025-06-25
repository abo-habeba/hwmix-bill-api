<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\AttributeValue\StoreAttributeValueRequest;
use App\Http\Requests\AttributeValue\UpdateAttributeValueRequest;
use App\Http\Resources\AttributeValue\AttributeValueResource;
use App\Models\AttributeValue;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class AttributeValueController
 *
 * تحكم في عمليات قيم السمات (عرض، إضافة، تعديل، حذف)
 *
 * @package App\Http\Controllers
 */
class AttributeValueController extends Controller
{
    /**
     * عرض جميع قيم السمات.
     *
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function index()
    {
        $attributeValues = AttributeValue::all();
        return AttributeValueResource::collection($attributeValues);
    }

    /**
     * إضافة قيمة سمة جديدة.
     *
     * @param StoreAttributeValueRequest $request
     * @return AttributeValueResource
     */
    public function store(StoreAttributeValueRequest $request)
    {
        $authUser = Auth::user();
        $validatedData = $request->validated();
        $validatedData['created_by'] = $validatedData['created_by'] ?? $authUser->id;
        $attributeValue = AttributeValue::create($validatedData);
        return new AttributeValueResource($attributeValue);
    }

    /**
     * عرض قيمة سمة محددة.
     *
     * @param string $id
     * @return AttributeValueResource
     */
    public function show(string $id)
    {
        $attributeValue = AttributeValue::findOrFail($id);
        return new AttributeValueResource($attributeValue);
    }

    /**
     * تحديث قيمة سمة.
     *
     * @param UpdateAttributeValueRequest $request
     * @param string $id
     * @return AttributeValueResource
     */
    public function update(UpdateAttributeValueRequest $request, string $id)
    {
        $attributeValue = AttributeValue::findOrFail($id);
        $attributeValue->update($request->validated());
        return new AttributeValueResource($attributeValue);
    }

    /**
     * حذف قيمة سمة.
     *
     * @param string $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(string $id)
    {
        $attributeValue = AttributeValue::findOrFail($id);
        $attributeValue->delete();

        return response()->noContent();
    }
}
