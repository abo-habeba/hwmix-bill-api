<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Resources\UserResource;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    // تسجيل مستخدم جديد
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
            'nickname' => Hash::make($validated['nickname']),
            'password' => Hash::make($validated['password']),
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'User registered successfully.',
            'user' => $user,
            'token' => $token,
        ], 201);
    }

    // تسجيل الدخول
    public function login(Request $request)
    {
        $validated = $request->validate([
            'phone' => 'required',
            'password' => 'required',
        ]);

        if (!Auth::attempt($validated)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

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
        if (auth()->check()) {
            return response()->json([
                'status' => 'logged_in',
                'user' => auth()->user(), // يمكنك إعادة بيانات المستخدم إذا أردت
            ], 200);
        }

        return response()->json([
            'status' => 'not_logged_in',
            'message' => 'User is not authenticated',
        ], 401);
    }
}
