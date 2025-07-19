<?php

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Pagination\AbstractPaginator;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * ✅ إرجاع استجابة ناجحة
 */
if (!function_exists('api_success')) {
    function api_success($data = [], string $message = 'تم بنجاح', int $code = 200): JsonResponse
    {
        // إذا كانت البيانات هي عبارة عن Paginator (للباجينيشن)
        if ($data instanceof AbstractPaginator) {
            return response()->json([
                'status' => true,
                'message' => $message,
                'data' => $data->items(),
                'total' => $data->total(),
                'page' => $data->currentPage(),
                'per_page' => $data->perPage(),
            ], $code);
        }

        // إذا كانت البيانات هي عبارة عن Collection Resource
        if ($data instanceof \Illuminate\Http\Resources\Json\ResourceCollection) {
            $original = $data->resource;

            if ($original instanceof \Illuminate\Pagination\AbstractPaginator) {
                return response()->json([
                    'data' => $data->collection,
                    'total' => $original->total(),
                ], $code);
            }
        }
        // إذا كانت البيانات هي عبارة عن Single Resource
        if ($data instanceof \Illuminate\Http\Resources\Json\JsonResource) {
            return response()->json([
                'status' => true,
                'message' => $message,
                'data' => $data,
            ], $code);
        }

        // إذا كانت البيانات عادية (مثال: Array أو Object)
        return response()->json([
            'status' => true,
            'message' => $message,
            'data' => $data,
        ], $code);
    }
}
/**
 * ❌ إرجاع استجابة خطأ منطقي أو تحقق
 */
if (!function_exists('api_error')) {
    function api_error(string $message = 'حدث خطأ ما', array $errors = [], int $code = 400): JsonResponse
    {
        return response()->json([
            'status' => false,
            'message' => $message,
            'errors' => $errors,
        ], $code);
    }
}

/**
 * 💥 إرجاع استجابة في حالة Exception
 */
if (!function_exists('api_exception')) {
    function api_exception(Throwable $e, int $code = 500, string $message = 'خطأ في جلب البيانات'): JsonResponse
    {
        // التعامل مع أنواع محددة من الأخطاء
        if ($e instanceof ValidationException) {
            return response()->json([
                'status' => false,
                'message' => 'خطأ في التحقق من البيانات',
                'errors' => $e->errors(),
            ], 422);
        } elseif ($e instanceof ModelNotFoundException) {
            return response()->json([
                'status' => false,
                'message' => 'السجل غير موجود',
            ], 404);
        }

        // تجميع تفاصيل الخطأ
        $errorDetails = [
            'status' => false,
            'message' => $message,
            'error' => $e->getMessage(),
            'exception' => get_class($e),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => config('app.debug') ? $e->getTraceAsString() : [],
        ];

        // تسجيل الخطأ
        Log::error('تفاصيل الخطأ:', $errorDetails);

        // إرجاع استجابة JSON
        return response()->json($errorDetails, $code);
    }
}

/**
 * 🛑 استجابة: لم يتم العثور على المورد
 */
if (!function_exists('api_not_found')) {
    function api_not_found(string $message = 'المورد غير موجود'): JsonResponse
    {
        return api_error($message, [], 404);
    }
}

/**
 * 🔐 استجابة: غير مصرح
 */
if (!function_exists('api_unauthorized')) {
    function api_unauthorized(string $message = 'غير مصرح بالدخول'): JsonResponse
    {
        return api_error($message, [], 401);
    }
}

/**
 * 🚫 استجابة: ممنوع الوصول
 */
if (!function_exists('api_forbidden')) {
    function api_forbidden(string $message = 'ممنوع الوصول لهذا المورد'): JsonResponse
    {
        return api_error($message, [], 403);
    }
}

/**
 * 📭 استجابة: لا يوجد بيانات
 */
if (!function_exists('api_no_content')) {
    function api_no_content(string $message = 'لا توجد بيانات'): JsonResponse
    {
        return api_error($message, [], 204);
    }
}
