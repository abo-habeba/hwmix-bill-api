<?php

namespace App\Http\Controllers;

use App\Models\Profit;
use App\Http\Requests\StoreProfitRequest;
use App\Http\Resources\ProfitResource;
use Illuminate\Http\Request;

class ProfitController extends Controller
{
    public function index(Request $request)
    {
        $query = Profit::query();
        if ($request->has('company_id')) {
            $query->where('company_id', $request->company_id);
        }
        $profits = $query->paginate($request->get('per_page', 15));
        return ProfitResource::collection($profits);
    }

    public function store(StoreProfitRequest $request)
    {
        $profit = Profit::create($request->validated());
        return new ProfitResource($profit);
    }

    public function show(Profit $profit)
    {
        return new ProfitResource($profit);
    }

    public function update(StoreProfitRequest $request, Profit $profit)
    {
        $profit->update($request->validated());
        return new ProfitResource($profit);
    }

    public function destroy(Profit $profit)
    {
        $profit->delete();
        return response()->noContent();
    }
}
