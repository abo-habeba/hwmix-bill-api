<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Resources\User\UserResource;
use App\Models\CashBox;
use App\Models\CashBoxType;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

/**
 * Class AuthController
 *
 * تحكم في عمليات التسجيل وتسجيل الدخول للمستخدمين
 *
 * @package App\Http\Controllers
 */
class AuthController extends Controller
{
    /**
     * تسجيل مستخدم جديد.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function register(Request $request)
    {
        $validated = $request->validate([
            'phone' => 'required|unique:users,phone,',
            'password' => 'required|string|min:8',
            'email' => 'nullable|email|unique:users,email,',
            'full_name' => 'nullable|string|max:255',
            'nickname' => 'nullable|string|max:255',
        ]);

        $user = User::create([
            'phone' => $validated['phone'],
            'full_name' => $validated['full_name'],
            'nickname' => $validated['nickname'],
            'password' => Hash::make($validated['password']),
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;
        $cashBoxType = CashBoxType::where('description', 'النوع الافتراضي للسيستم')->first();
        // إنشاء خزنة للمستخدم
        CashBox::create([
            'name' => 'نقدي',
            'balance' => 0,
            'cash_box_type_id' => $cashBoxType->id,
            'is_default' => true,
            'account_number' => $user->id,
            'user_id' => $user->id,
            'created_by' => $user->id,
            'company_id' => $user->company_id,
        ]);
        return response()->json([
            'message' => 'User registered successfully.',
            'user' => $user,
            'token' => $token,
        ], 201);
    }

    /**
     * تسجيل الدخول للمستخدم.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(Request $request)
    {
        $validated = $request->validate([
            'login' => 'required',
            'password' => 'required',
        ]);
        $loginField = filter_var($validated['login'], FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';
        if (!Auth::attempt([$loginField => $validated['login'], 'password' => $validated['password']])) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'User logged in successfully.',
            'user' => new UserResource($user),
            'token' => $token,
        ]);
    }

    // تسجيل الخروج
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully.']);
    }

    // استعادة بيانات المستخدم
    public function me(Request $request)
    {
        return response()->json($request->user());
    }

    public function checkLogin(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        if (Auth::check()) {
            return response()->json([
                'status' => 'logged_in',
                'user' => $user,  // يمكنك إعادة بيانات المستخدم إذا أردت
            ], 200);
        }

        return response()->json([
            'status' => 'not_logged_in',
            'message' => 'User is not authenticated',
        ], 401);
    }
}
