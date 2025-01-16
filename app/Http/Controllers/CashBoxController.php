<?php

namespace App\Http\Controllers;

use App\Http\Resources\CashBoxResource;
use App\Models\CashBox;
use Illuminate\Http\Request;

class CashBoxController extends Controller
{
    public function index()
    {
        $cashBoxes = CashBox::with(['user', 'company'])->get();
        return CashBoxResource::collection($cashBoxes);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'balance' => 'nullable|numeric',
            'user_id' => 'required|exists:users,id',
            'company_id' => 'required|exists:companies,id',
        ]);

        $cashBox = CashBox::create($validated);

        return new CashBoxResource($cashBox->load(['user', 'company']));
    }

    public function show(CashBox $cashBox)
    {
        return new CashBoxResource($cashBox->load(['user', 'company']));
    }

    public function update(Request $request, CashBox $cashBox)
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'balance' => 'nullable|numeric',
            'user_id' => 'sometimes|exists:users,id',
            'company_id' => 'sometimes|exists:companies,id',
        ]);

        $cashBox->update($validated);

        return new CashBoxResource($cashBox->load(['user', 'company']));
    }

    public function destroy(CashBox $cashBox)
    {
        $cashBox->delete();

        return response()->noContent();
    }
}
