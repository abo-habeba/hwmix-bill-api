<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Resources\User\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException; // استيراد ValidationException
use Throwable; // استيراد Throwable

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
    public function register(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'phone' => 'required|unique:users,phone',
                'password' => 'required|string|min:8',
                'email' => 'nullable|email|unique:users,email',
                'full_name' => 'nullable|string|max:255',
                'nickname' => 'nullable|string|max:255',
            ]);

            $user = User::create([
                'phone' => $validated['phone'],
                'full_name' => $validated['full_name'] ?? null,
                'nickname' => $validated['nickname'] ?? null,
                'password' => Hash::make($validated['password']),
            ]);

            $token = $user->createToken('auth_token')->plainTextToken;
            // إنشاء صناديق المستخدم الافتراضية لكل شركة
            $user->ensureCashBoxesForAllCompanies();

            return api_success([
                'user' => new UserResource($user),
                'token' => $token,
            ], 'تم تسجيل المستخدم بنجاح.', 201);
        } catch (ValidationException $e) {
            return api_error('فشل التحقق من صحة البيانات أثناء التسجيل.', $e->errors(), 422);
        } catch (Throwable $e) {
            return api_exception($e, 500);
        }
    }

    /**
     * تسجيل الدخول للمستخدم.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'login' => 'required',
                'password' => 'required',
            ]);

            $loginField = filter_var($validated['login'], FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';

            if (!Auth::attempt([$loginField => $validated['login'], 'password' => $validated['password']])) {
                return api_unauthorized('بيانات الاعتماد غير صالحة.');
            }

            /** @var \App\Models\User $user */
            $user = Auth::user();
            $token = $user->createToken('auth_token')->plainTextToken;

            return api_success([
                'user' => new UserResource($user),
                'token' => $token,
            ], 'تم تسجيل دخول المستخدم بنجاح.');
        } catch (ValidationException $e) {
            return api_error('فشل التحقق من صحة البيانات أثناء تسجيل الدخول.', $e->errors(), 422);
        } catch (Throwable $e) {
            return api_exception($e, 500);
        }
    }

    /**
     * تسجيل الخروج للمستخدم.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout(Request $request): JsonResponse
    {
        try {
            /** @var \App\Models\User $user */
            /** @var \Laravel\Sanctum\PersonalAccessToken|null $token */
            $user = $request->user();
            if ($user) {
                $user->currentAccessToken()->delete();
            } else {
                // إذا لم يكن هناك مستخدم مصادق عليه، فلا يوجد رمز مميز لحذفه
                return api_error('لا يوجد مستخدم مصادق عليه لتسجيل الخروج.', [], 401);
            }

            return api_success([], 'تم تسجيل الخروج بنجاح.');
        } catch (Throwable $e) {
            return api_exception($e, 500);
        }
    }

    /**
     * استعادة بيانات المستخدم المصادق عليه.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function me(Request $request): JsonResponse
    {
        try {
            /** @var \App\Models\User $user */
            $user = $request->user();

            if (!$user) {
                return api_unauthorized('المستخدم غير مصادق عليه.');
            }

            return api_success(new UserResource($user), 'تم استرداد بيانات المستخدم بنجاح.');
        } catch (Throwable $e) {
            return api_exception($e, 500);
        }
    }

    /**
     * التحقق من حالة تسجيل دخول المستخدم.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function checkLogin(Request $request): JsonResponse
    {
        try {
            /** @var \App\Models\User $user */
            $user = Auth::user();

            if (Auth::check()) {
                return api_success(new UserResource($user), 'المستخدم مسجل الدخول.');
            }

            return api_unauthorized('المستخدم غير مصادق عليه.');
        } catch (Throwable $e) {
            return api_exception($e, 500);
        }
    }
}
