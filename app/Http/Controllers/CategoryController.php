<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Category\StoreCategoryRequest;
use App\Http\Requests\Category\UpdateCategoryRequest;
use App\Http\Resources\Category\CategoryResource;
use App\Models\Category;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Category::with('children.children')->whereNull('parent_id');
        // التحقق من وجود قيمة البحث
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where('name', 'LIKE', "%$search%");
        }
        $categories = $query->get();

        return CategoryResource::collection($categories);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreCategoryRequest $request)
    {
        $authUser = Auth::user();
        $validatedData = $request->validated();
        $validatedData['company_id'] = $validatedData['company_id'] ?? $authUser->company_id;
        $validatedData['created_by'] = $validatedData['created_by'] ?? $authUser->id;
        // return $validatedData;
        $category = Category::create($validatedData);
        return new CategoryResource($category);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $category = Category::with('children.children')->findOrFail($id);
        return new CategoryResource($category);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateCategoryRequest $request, string $id)
    {
        $category = Category::findOrFail($id);
        $category->update($request->validated());
        return new CategoryResource($category);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $category = Category::findOrFail($id);
        $category->delete();

        return response()->noContent();
    }
}
