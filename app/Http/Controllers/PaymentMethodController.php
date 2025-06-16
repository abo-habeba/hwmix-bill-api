<?php
namespace App\Http\Controllers;

use App\Models\PaymentMethod;
use Illuminate\Http\Request;

class PaymentMethodController extends Controller
{
    public function index()
    {
        return PaymentMethod::where('active', true)->get();
    }

    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:255|unique:payment_methods,code',
            'active' => 'required|boolean',
        ]);

        $paymentMethod = PaymentMethod::create($validatedData);

        return response()->json($paymentMethod);
    }

    public function show($id)
    {
        return response()->json(PaymentMethod::findOrFail($id));
    }

    public function update(Request $request, $id)
    {
        $paymentMethod = PaymentMethod::findOrFail($id);

        $validatedData = $request->validate([
            'name' => 'sometimes|string|max:255',
            'code' => 'sometimes|string|max:255|unique:payment_methods,code,' . $id,
            'active' => 'sometimes|boolean',
        ]);

        $paymentMethod->update($validatedData);

        return response()->json($paymentMethod);
    }

    public function destroy($id)
    {
        $paymentMethod = PaymentMethod::findOrFail($id);
        $paymentMethod->delete();

        return response()->json(['message' => 'Deleted successfully']);
    }
}
