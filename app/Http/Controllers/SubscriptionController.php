<?php
namespace App\Http\Controllers;

use App\Models\Subscription;
use App\Http\Requests\Subscription\StoreSubscriptionRequest;
use App\Http\Requests\Subscription\UpdateSubscriptionRequest;
use App\Http\Resources\Subscription\SubscriptionResource;

class SubscriptionController extends Controller
{
    public function index()
    {
        return SubscriptionResource::collection(Subscription::paginate(20));
    }
    public function store(StoreSubscriptionRequest $request)
    {
        $subscription = Subscription::create($request->validated());
        return new SubscriptionResource($subscription);
    }
    public function show($id)
    {
        return new SubscriptionResource(Subscription::findOrFail($id));
    }
    public function update(UpdateSubscriptionRequest $request, $id)
    {
        $subscription = Subscription::findOrFail($id);
        $subscription->update($request->validated());
        return new SubscriptionResource($subscription);
    }
    public function destroy($id)
    {
        $subscription = Subscription::findOrFail($id);
        $subscription->delete();
        return response()->json(['message' => 'Deleted successfully']);
    }
}
