<?php

namespace App\Http\Controllers;

use App\Models\Revenue;
use App\Http\Requests\StoreRevenueRequest;
use App\Http\Resources\RevenueResource;
use Illuminate\Http\Request;

class RevenueController extends Controller
{
    public function index(Request $request)
    {
        $query = Revenue::query();
        if ($request->has('company_id')) {
            $query->where('company_id', $request->company_id);
        }
        $revenues = $query->paginate($request->get('per_page', 15));
        return RevenueResource::collection($revenues);
    }

    public function store(StoreRevenueRequest $request)
    {
        $revenue = Revenue::create($request->validated());
        return new RevenueResource($revenue);
    }

    public function show(Revenue $revenue)
    {
        return new RevenueResource($revenue);
    }

    public function update(StoreRevenueRequest $request, Revenue $revenue)
    {
        $revenue->update($request->validated());
        return new RevenueResource($revenue);
    }

    public function destroy(Revenue $revenue)
    {
        $revenue->delete();
        return response()->noContent();
    }
}
