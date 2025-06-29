<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProductVariant\StoreProductVariantRequest;
use App\Http\Requests\ProductVariant\UpdateProductVariantRequest;
use App\Http\Resources\ProductVariant\ProductVariantResource;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Stock; // تم إضافة هذا الاستيراد
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth; // تم إضافة هذا الاستيراد
use Illuminate\Support\Facades\DB;   // تم إضافة هذا الاستيراد
use Illuminate\Support\Facades\Log;  // تم إضافة هذا الاستيراد
use Illuminate\Validation\ValidationException; // تم إضافة هذا الاستيراد
use Throwable; // تم إضافة هذا الاستيراد

// دالة مساعدة لضمان الاتساق في مفاتيح الأذونات (إذا لم تكن معرفة عالميا)
// if (!function_exists('perm_key')) {
//     function perm_key(string $permission): string
//     {
//         return $permission;
//     }
// }

class ProductVariantController extends Controller
{
    protected array $relations = [
        'creator',
        'company', // علاقة مباشرة بالشركة للمتغير
        'product', // المنتج الأم
        'product.creator',
        'product.company',
        'product.category',
        'product.brand',
        'attributes.attribute',
        'attributes.attributeValue',
        'stocks.warehouse',
    ];

    /**
     * عرض قائمة بمتغيرات المنتجات مع الفلاتر والصلاحيات.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function index(Request $request)
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $query = ProductVariant::with($this->relations);
            $companyId = $authUser->company_id;

            // التحقق الأساسي: إذا لم يكن المستخدم مرتبطًا بشركة وليس سوبر أدمن
            if (!$companyId && !$authUser->hasPermissionTo(perm_key('admin.super'))) {
                return response()->json(['error' => 'Unauthorized', 'message' => 'User is not associated with a company.'], 403);
            }

            // تطبيق منطق الصلاحيات
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                // المسؤول العام يرى جميع المتغيرات (لا قيود إضافية)
            } elseif ($authUser->hasAnyPermission([perm_key('product_variants.view_all'), perm_key('admin.company')])) {
                // يرى جميع المتغيرات الخاصة بالشركة النشطة (بما في ذلك مديرو الشركة)
                $query->whereCompanyIsCurrent();
            } elseif ($authUser->hasPermissionTo(perm_key('product_variants.view_children'))) {
                // يرى المتغيرات التي أنشأها المستخدم أو المستخدمون التابعون له، ضمن الشركة النشطة
                $query->whereCompanyIsCurrent()->whereCreatedByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('product_variants.view_self'))) {
                // يرى المتغيرات التي أنشأها المستخدم فقط، ومرتبطة بالشركة النشطة
                $query->whereCompanyIsCurrent()->whereCreatedByUser();
            } else {
                return response()->json(['error' => 'Unauthorized', 'message' => 'You are not authorized to view product variants.'], 403);
            }

            // تطبيق فلاتر البحث
            if ($request->filled('search')) {
                $search = $request->input('search');
                $query->where(function ($q) use ($search) {
                    $q
                        ->where('sku', 'like', "%$search%")
                        ->orWhere('barcode', 'like', "%$search%")
                        ->orWhereHas('product', function ($pq) use ($search) {
                            $pq->where('name', 'like', "%$search%")
                                ->orWhere('desc', 'like', "%$search%");
                        });
                });
            }
            if ($request->filled('product_id')) {
                $query->where('product_id', $request->input('product_id'));
            }
            if ($request->filled('status')) {
                $query->where('status', $request->input('status'));
            }

            // الفرز والتصفح
            $sortBy = $request->input('sort_by', 'created_at');
            $sortOrder = $request->input('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);

            $perPage = max(1, (int) $request->get('per_page', 10));
            $productVariants = $query->paginate($perPage);

            return ProductVariantResource::collection($productVariants)->additional([
                'total' => $productVariants->total(),
                'current_page' => $productVariants->currentPage(),
                'last_page' => $productVariants->lastPage(),
            ]);
        } catch (Throwable $e) {
            Log::error('ProductVariant index failed: ' . $e->getMessage(), [
                'exception' => $e,
                'user_id' => Auth::id(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'error' => 'Error retrieving product variants.',
                'details' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param StoreProductVariantRequest $request
     * @return \Illuminate\Http\JsonResponse|\App\Http\Resources\ProductVariant\ProductVariantResource
     */
    public function store(StoreProductVariantRequest $request)
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->company_id;

            if (!$authUser || !$companyId) {
                return response()->json(['error' => 'Unauthorized', 'message' => 'Authentication or company association required.'], 403);
            }

            // صلاحيات إنشاء متغير منتج
            if (!$authUser->hasPermissionTo(perm_key('admin.super')) && !$authUser->hasPermissionTo(perm_key('product_variants.create')) && !$authUser->hasPermissionTo(perm_key('admin.company'))) {
                return response()->json([
                    'error' => 'Unauthorized',
                    'message' => 'You are not authorized to create product variants.'
                ], 403);
            }

            DB::beginTransaction();
            try {
                $validated = $request->validated();

                // التحقق من أن المنتج الأم تابع لشركة المستخدم أو أن المستخدم super_admin
                $product = Product::with('company')->find($validated['product_id']);
                if (!$product || (!$authUser->hasPermissionTo(perm_key('admin.super')) && $product->company_id !== $companyId)) {
                    DB::rollBack();
                    return response()->json(['error' => 'Forbidden', 'message' => 'Product not found or not accessible within your company.'], 403);
                }

                // إذا كان المستخدم super_admin ويحدد company_id، يسمح بذلك. وإلا، استخدم company_id للمنتج الأم.
                $variantCompanyId = ($authUser->hasPermissionTo(perm_key('admin.super')) && isset($validated['company_id']))
                    ? $validated['company_id']
                    : $product->company_id; // استخدم company_id للمنتج الأم

                // التأكد من أن المستخدم مصرح له بإنشاء متغير لهذه الشركة
                if ($variantCompanyId != $companyId && !$authUser->hasPermissionTo(perm_key('admin.super'))) {
                    DB::rollBack();
                    return response()->json(['error' => 'Unauthorized', 'message' => 'You can only create product variants for your current company unless you are a Super Admin.'], 403);
                }

                $validated['company_id'] = $variantCompanyId;
                $validated['created_by'] = $authUser->id; // من أنشأ المتغير

                $productVariant = ProductVariant::create($validated);

                // حفظ الخصائص (attributes)
                if ($request->has('attributes') && is_array($request->input('attributes'))) {
                    foreach ($request->input('attributes') as $attr) {
                        if (!empty($attr['attribute_id']) && !empty($attr['attribute_value_id'])) {
                            $productVariant->attributes()->create([
                                'attribute_id' => $attr['attribute_id'],
                                'attribute_value_id' => $attr['attribute_value_id'],
                                'company_id' => $variantCompanyId, // ربط بنفس شركة المتغير
                                'created_by' => $authUser->id, // من أنشأ الخاصية
                            ]);
                        }
                    }
                }

                // حفظ المخزون (stocks) - لاحظ أن العلاقة هي 'stocks' وليس 'stock' إذا كان يمكن أن يكون هناك مخزون متعدد
                if ($request->has('stocks') && is_array($request->input('stocks'))) {
                    foreach ($request->input('stocks') as $stockData) {
                        if (!empty($stockData['warehouse_id'])) {
                            Stock::create([
                                'product_variant_id' => $productVariant->id,
                                'warehouse_id' => $stockData['warehouse_id'],
                                'quantity' => $stockData['quantity'] ?? 0,
                                'reserved' => $stockData['reserved'] ?? 0,
                                'min_quantity' => $stockData['min_quantity'] ?? 0,
                                'cost' => $stockData['cost'] ?? null,
                                'batch' => $stockData['batch'] ?? null,
                                'expiry' => $stockData['expiry'] ?? null,
                                'loc' => $stockData['loc'] ?? null,
                                'status' => $stockData['status'] ?? 'available',
                                'company_id' => $variantCompanyId, // ربط بنفس شركة المتغير
                                'created_by' => $authUser->id, // من أنشأ سجل المخزون
                            ]);
                        }
                    }
                }

                DB::commit();
                Log::info('Product variant created successfully.', ['product_variant_id' => $productVariant->id, 'product_id' => $product->id, 'user_id' => $authUser->id, 'company_id' => $companyId]);
                return new ProductVariantResource($productVariant->load($this->relations));
            } catch (ValidationException $e) {
                DB::rollBack();
                Log::error('ProductVariant store validation failed: ' . $e->getMessage(), [
                    'errors' => $e->errors(),
                    'user_id' => Auth::id(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
                return response()->json([
                    'status' => 'error',
                    'message' => $e->getMessage(),
                    'errors' => $e->errors(),
                ], 422);
            } catch (Throwable $e) {
                DB::rollBack();
                Log::error('ProductVariant store failed in transaction: ' . $e->getMessage(), [
                    'exception' => $e,
                    'user_id' => Auth::id(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                ]);
                return response()->json([
                    'error' => 'Error saving product variant.',
                    'details' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ], 500);
            }
        } catch (Throwable $e) {
            Log::error('ProductVariant store failed: ' . $e->getMessage(), [
                'exception' => $e,
                'user_id' => Auth::id(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'error' => 'Error saving product variant.',
                'details' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param string $id
     * @return \Illuminate\Http\JsonResponse|\App\Http\Resources\ProductVariant\ProductVariantResource
     */
    public function show(string $id)
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->company_id;

            if (!$authUser || !$companyId) {
                return response()->json(['error' => 'Unauthorized', 'message' => 'Authentication or company association required.'], 403);
            }

            $productVariant = ProductVariant::with($this->relations)->findOrFail($id);

            // التحقق من صلاحيات العرض
            $canView = false;
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                $canView = true; // المسؤول العام يرى أي متغير
            } elseif ($authUser->hasAnyPermission([perm_key('product_variants.view_all'), perm_key('admin.company')])) {
                // يرى إذا كان المتغير ينتمي للشركة النشطة (بما في ذلك مديرو الشركة)
                $canView = $productVariant->belongsToCurrentCompany();
            } elseif ($authUser->hasPermissionTo(perm_key('product_variants.view_children'))) {
                // يرى إذا كان المتغير أنشأه هو أو أحد التابعين له وتابع للشركة النشطة
                $canView = $productVariant->belongsToCurrentCompany() && $productVariant->createdByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('product_variants.view_self'))) {
                // يرى إذا كان المتغير أنشأه هو وتابع للشركة النشطة
                $canView = $productVariant->belongsToCurrentCompany() && $productVariant->createdByCurrentUser();
            } else {
                return response()->json(['error' => 'Unauthorized', 'message' => 'You are not authorized to view this product variant.'], 403);
            }

            if ($canView) {
                return new ProductVariantResource($productVariant);
            }

            return response()->json(['error' => 'Unauthorized', 'message' => 'You are not authorized to view this product variant.'], 403);
        } catch (Throwable $e) {
            Log::error('ProductVariant show failed: ' . $e->getMessage(), [
                'exception' => $e,
                'user_id' => Auth::id(),
                'product_variant_id' => $id,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'error' => 'Error retrieving product variant.',
                'details' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param UpdateProductVariantRequest $request
     * @param string $id
     * @return \Illuminate\Http\JsonResponse|\App\Http\Resources\ProductVariant\ProductVariantResource
     */
    public function update(UpdateProductVariantRequest $request, string $id)
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->company_id;

            if (!$authUser || !$companyId) {
                return response()->json(['error' => 'Unauthorized', 'message' => 'Authentication or company association required.'], 403);
            }

            // يجب تحميل العلاقات الضرورية للتحقق من الصلاحيات (مثل الشركة والمنشئ)
            $productVariant = ProductVariant::with(['company', 'creator', 'product'])->findOrFail($id);

            // التحقق من صلاحيات التحديث
            $canUpdate = false;
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                $canUpdate = true; // المسؤول العام يمكنه تعديل أي متغير
            } elseif ($authUser->hasAnyPermission([perm_key('product_variants.update_any'), perm_key('admin.company')])) {
                // يمكنه تعديل أي متغير داخل الشركة النشطة (بما في ذلك مديرو الشركة)
                $canUpdate = $productVariant->belongsToCurrentCompany();
            } elseif ($authUser->hasPermissionTo(perm_key('product_variants.update_children'))) {
                // يمكنه تعديل المتغيرات التي أنشأها هو أو أحد التابعين له وتابعة للشركة النشطة
                $canUpdate = $productVariant->belongsToCurrentCompany() && $productVariant->createdByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('product_variants.update_self'))) {
                // يمكنه تعديل متغيره الخاص الذي أنشأه وتابع للشركة النشطة
                $canUpdate = $productVariant->belongsToCurrentCompany() && $productVariant->createdByCurrentUser();
            } else {
                return response()->json(['error' => 'Unauthorized', 'message' => 'You are not authorized to update this product variant.'], 403);
            }

            if (!$canUpdate) {
                return response()->json(['error' => 'Unauthorized', 'message' => 'You are not authorized to update this product variant.'], 403);
            }

            DB::beginTransaction();
            try {
                $validated = $request->validated();
                $updatedBy = $authUser->id;

                // التحقق من أن المنتج الأم المحدث (إذا تم إرساله) تابع لشركة المستخدم أو أن المستخدم super_admin
                if (isset($validated['product_id']) && $validated['product_id'] != $productVariant->product_id) {
                    $newProduct = Product::with('company')->find($validated['product_id']);
                    if (!$newProduct || (!$authUser->hasPermissionTo(perm_key('admin.super')) && $newProduct->company_id !== $companyId)) {
                        DB::rollBack();
                        return response()->json(['error' => 'Forbidden', 'message' => 'New product not found or not accessible within your company.'], 403);
                    }
                }

                // إذا كان المستخدم سوبر ادمن ويحدد معرف الشركه، يسمح بذلك. وإلا، استخدم معرف الشركه للمتغير.
                $variantCompanyId = ($authUser->hasPermissionTo(perm_key('admin.super')) && isset($validated['company_id']))
                    ? $validated['company_id']
                    : $productVariant->company_id;

                // التأكد من أن المستخدم مصرح له بتعديل متغير لشركة أخرى (فقط سوبر أدمن)
                if ($variantCompanyId != $productVariant->company_id && !$authUser->hasPermissionTo(perm_key('admin.super'))) {
                    DB::rollBack();
                    return response()->json(['error' => 'Unauthorized', 'message' => 'You cannot change a product variant\'s company unless you are a Super Admin.'], 403);
                }

                $validated['company_id'] = $variantCompanyId; // تحديث company_id في البيانات المصدقة
                $validated['updated_by'] = $updatedBy; // من قام بالتعديل

                $productVariant->update($validated);

                // تحديث الخصائص (attributes)
                $requestedAttributeIds = collect($request->input('attributes') ?? [])
                    ->pluck('id')->filter()->all();
                $productVariant->attributes()->whereNotIn('id', $requestedAttributeIds)->delete(); // حذف الخصائص المحذوفة

                if ($request->has('attributes') && is_array($request->input('attributes'))) {
                    foreach ($request->input('attributes') as $attr) {
                        if (!empty($attr['attribute_id']) && !empty($attr['attribute_value_id'])) {
                            // التحقق من وجود الخاصية وتحديثها أو إنشائها
                            $productVariant->attributes()->updateOrCreate(
                                ['id' => $attr['id'] ?? null], // إذا كان هناك ID يتم تحديثه، وإلا يتم إنشاء جديد
                                [
                                    'attribute_id' => $attr['attribute_id'],
                                    'attribute_value_id' => $attr['attribute_value_id'],
                                    'company_id' => $variantCompanyId, // ربط بنفس شركة المتغير
                                    'created_by' => $attr['created_by'] ?? $authUser->id, // إذا تم تحديدها أو المستخدم الحالي
                                    'updated_by' => $authUser->id,
                                ]
                            );
                        }
                    }
                }

                // تحديث سجلات المخزون (stocks)
                $requestedStockIds = collect($request->input('stocks') ?? [])->pluck('id')->filter()->all();
                $productVariant->stocks()->whereNotIn('id', $requestedStockIds)->delete(); // حذف سجلات المخزون المحذوفة

                if ($request->has('stocks') && is_array($request->input('stocks'))) {
                    foreach ($request->input('stocks') as $stockData) {
                        if (!empty($stockData['warehouse_id'])) {
                            Stock::updateOrCreate(
                                ['id' => $stockData['id'] ?? null, 'product_variant_id' => $productVariant->id],
                                [
                                    'warehouse_id' => $stockData['warehouse_id'],
                                    'quantity' => $stockData['quantity'] ?? 0,
                                    'reserved' => $stockData['reserved'] ?? 0,
                                    'min_quantity' => $stockData['min_quantity'] ?? 0,
                                    'cost' => $stockData['cost'] ?? null,
                                    'batch' => $stockData['batch'] ?? null,
                                    'expiry' => $stockData['expiry'] ?? null,
                                    'loc' => $stockData['loc'] ?? null,
                                    'status' => $stockData['status'] ?? 'available',
                                    'company_id' => $variantCompanyId, // ربط بنفس شركة المتغير
                                    'created_by' => $stockData['created_by'] ?? $authUser->id,
                                    'updated_by' => $authUser->id,
                                ]
                            );
                        }
                    }
                } else {
                    $productVariant->stocks()->delete(); // حذف كل المخزون إذا لم يتم إرسال أي بيانات
                }


                DB::commit();
                Log::info('Product variant updated successfully.', ['product_variant_id' => $productVariant->id, 'user_id' => $authUser->id, 'company_id' => $companyId]);
                return new ProductVariantResource($productVariant->load($this->relations));
            } catch (ValidationException $e) {
                DB::rollBack();
                Log::error('ProductVariant update validation failed: ' . $e->getMessage(), [
                    'errors' => $e->errors(),
                    'user_id' => Auth::id(),
                    'product_variant_id' => $id,
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
                return response()->json([
                    'status' => 'error',
                    'message' => $e->getMessage(),
                    'errors' => $e->errors(),
                ], 422);
            } catch (Throwable $e) {
                DB::rollBack();
                Log::error('ProductVariant update failed in transaction: ' . $e->getMessage(), [
                    'exception' => $e,
                    'user_id' => Auth::id(),
                    'product_variant_id' => $id,
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                ]);
                return response()->json([
                    'error' => 'Error updating product variant.',
                    'details' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ], 500);
            }
        } catch (Throwable $e) {
            Log::error('ProductVariant update failed: ' . $e->getMessage(), [
                'exception' => $e,
                'user_id' => Auth::id(),
                'product_variant_id' => $id,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'error' => 'Error updating product variant.',
                'details' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(string $id)
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->company_id;

            if (!$authUser || !$companyId) {
                return response()->json(['error' => 'Unauthorized', 'message' => 'Authentication or company association required.'], 403);
            }

            // يجب تحميل العلاقات الضرورية للتحقق من الصلاحيات (مثل الشركة والمنشئ)
            $productVariant = ProductVariant::with(['company', 'creator'])->findOrFail($id);

            // التحقق من صلاحيات الحذف
            $canDelete = false;
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                $canDelete = true; // المسؤول العام يمكنه حذف أي متغير
            } elseif ($authUser->hasAnyPermission([perm_key('product_variants.delete_any'), perm_key('admin.company')])) {
                // يمكنه حذف أي متغير داخل الشركة النشطة (بما في ذلك مديرو الشركة)
                $canDelete = $productVariant->belongsToCurrentCompany();
            } elseif ($authUser->hasPermissionTo(perm_key('product_variants.delete_children'))) {
                // يمكنه حذف المتغيرات التي أنشأها هو أو أحد التابعين له وتابعة للشركة النشطة
                $canDelete = $productVariant->belongsToCurrentCompany() && $productVariant->createdByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('product_variants.delete_self'))) {
                // يمكنه حذف متغيره الخاص الذي أنشأه وتابع للشركة النشطة
                $canDelete = $productVariant->belongsToCurrentCompany() && $productVariant->createdByCurrentUser();
            } else {
                return response()->json(['error' => 'Unauthorized', 'message' => 'You are not authorized to delete this product variant.'], 403);
            }

            if (!$canDelete) {
                return response()->json(['error' => 'Unauthorized', 'message' => 'You are not authorized to delete this product variant.'], 403);
            }

            DB::beginTransaction();
            try {
                // حذف العلاقات التابعة لـ ProductVariant (Stocks, Attributes) أولاً
                $productVariant->stocks()->delete();
                $productVariant->attributes()->delete();
                $productVariant->delete();

                DB::commit();
                Log::info('Product variant deleted successfully.', ['product_variant_id' => $id, 'user_id' => $authUser->id, 'company_id' => $companyId]);
                return response()->json(['message' => 'Product variant deleted successfully'], 200);
            } catch (Throwable $e) {
                DB::rollBack();
                Log::error('ProductVariant deletion failed: ' . $e->getMessage(), [
                    'exception' => $e,
                    'user_id' => Auth::id(),
                    'product_variant_id' => $id,
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                ]);
                return response()->json([
                    'error' => 'Error deleting product variant.',
                    'details' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ], 500);
            }
        } catch (Throwable $e) {
            Log::error('ProductVariant deletion failed: ' . $e->getMessage(), [
                'exception' => $e,
                'user_id' => Auth::id(),
                'product_variant_id' => $id,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'error' => 'Error deleting product variant.',
                'details' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], 500);
        }
    }

    /**
     * البحث عن متغيرات منتج باستخدام براميتر بحث وتطبيق الصلاحيات.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function searchByProduct(Request $request)
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->company_id;

            if (!$authUser || !$companyId) {
                return response()->json(['error' => 'Unauthorized', 'message' => 'Authentication or company association required.'], 403);
            }

            $search = $request->get('search');
            // إذا كان البحث فارغًا أو قصيرًا جدًا، ارجع مجموعة فارغة
            if (empty($search) || mb_strlen($search) <= 2) {
                return ProductVariantResource::collection(collect([]));
            }

            $productQuery = Product::query();

            // تطبيق منطق الصلاحيات على استعلام المنتج
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                // المسؤول العام يرى جميع المنتجات
            } elseif ($authUser->hasAnyPermission([perm_key('products.view_all'), perm_key('admin.company'), perm_key('product_variants.view_all')])) { // يجب أن يكون لديه صلاحية رؤية المنتجات أو المتغيرات
                $productQuery->whereCompanyIsCurrent();
            } elseif ($authUser->hasAnyPermission([perm_key('products.view_children'), perm_key('product_variants.view_children')])) {
                $productQuery->whereCompanyIsCurrent()->whereCreatedByUserOrChildren();
            } elseif ($authUser->hasAnyPermission([perm_key('products.view_self'), perm_key('product_variants.view_self')])) {
                $productQuery->whereCompanyIsCurrent()->whereCreatedByUser();
            } else {
                // إذا لم يكن لديه صلاحية رؤية أي منتج أو متغير، ارجع مجموعة فارغة
                return ProductVariantResource::collection(collect([]));
            }

            $productQuery->where(function ($q) use ($search) {
                $q
                    ->where('name', 'like', "%$search%")
                    ->orWhere('desc', 'like', "%$search%");
            });

            $perPage = max(1, (int) $request->get('per_page', 10));

            $products = $productQuery->with(['variants' => function ($query) {
                // تحميل العلاقات المطلوبة للمتغيرات
                $query->with($this->relations);
            }])->paginate($perPage);

            // استخلاص جميع المتغيرات من المنتجات المفلترة
            $variants = collect($products->items())->flatMap(function ($product) {
                return $product->variants;
            });

            // إرجاع المتغيرات كـ ProductVariantResource
            return ProductVariantResource::collection($variants);
        } catch (Throwable $e) {
            Log::error('ProductVariant searchByProduct failed: ' . $e->getMessage(), [
                'exception' => $e,
                'user_id' => Auth::id(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'error' => 'Error searching product variants.',
                'details' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], 500);
        }
    }
}
