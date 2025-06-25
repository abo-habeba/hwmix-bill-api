<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Brand\StoreBrandRequest;
use App\Http\Requests\Brand\UpdateBrandRequest;
use App\Http\Resources\Brand\BrandResource;
use App\Models\Brand;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class BrandController
 *
 * تحكم في عمليات العلامات التجارية (عرض، إضافة، تعديل، حذف)
 *
 * @package App\Http\Controllers
 */
class BrandController extends Controller
{
    /**
     * عرض جميع العلامات التجارية.
     *
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function index()
    {
        $brands = Brand::all();
        return BrandResource::collection($brands);
    }

    /**
     * إضافة علامة تجارية جديدة.
     *
     * @param StoreBrandRequest $request
     * @return BrandResource
     */
    public function store(StoreBrandRequest $request)
    {
        $authUser = Auth::user();
        $validatedData = $request->validated();
        $validatedData['company_id'] = $validatedData['company_id'] ?? $authUser->company_id;
        $validatedData['created_by'] = $validatedData['created_by'] ?? $authUser->id;
        $brand = Brand::create($validatedData);
        return new BrandResource($brand);
    }

    /**
     * عرض علامة تجارية محددة.
     *
     * @param string $id
     * @return BrandResource
     */
    public function show(string $id)
    {
        $brand = Brand::findOrFail($id);
        return new BrandResource($brand);
    }

    /**
     * تحديث علامة تجارية.
     *
     * @param UpdateBrandRequest $request
     * @param string $id
     * @return BrandResource
     */
    public function update(UpdateBrandRequest $request, string $id)
    {
        $brand = Brand::findOrFail($id);
        $brand->update($request->validated());
        return new BrandResource($brand);
    }

    /**
     * حذف علامة تجارية.
     *
     * @param Brand $brand
     * @return \Illuminate\Http\Response
     */
    public function destroy(Brand $brand)
    {
        // تحقق من وجود منتجات مرتبطة
        if ($brand->products()->count() > 0) {
            return response()->json([
                'message' => 'لا يمكن حذف الماركة لوجود منتجات مرتبطة بها.'
            ], 400);
        }

        $brand->delete();
        return response()->noContent();
    }
}
