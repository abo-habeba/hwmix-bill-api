<?php

use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\AbstractPaginator;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * ✅ إرجاع استجابة ناجحة
 */
if (!function_exists('api_success')) {
    function api_success($data = [], string $message = 'تم بنجاح', int $code = 200): JsonResponse
    {
        $response = [
            'status'  => true,
            'message' => $message,
            'data'    => [],
        ];

        // ✅ إذا كانت البيانات Paginator عادي (للبجينيشن)
        if ($data instanceof AbstractPaginator) {
            $response['data']  = $data->items();
            $response['total'] = $data->total(); // مهم لـ v-data-table-server
            return response()->json($response, $code);
        }

        // ✅ إذا كانت ResourceCollection (قد تكون مع Pagination)
        if ($data instanceof ResourceCollection) {
            $original = $data->resource;

            // لو فيها Pagination
            if ($original instanceof AbstractPaginator) {
                $response['data']  = $data->collection;
                $response['total'] = $original->total();
            } else {
                $response['data'] = $data->collection;
                $response['total'] = $data->count(); // عدد العناصر
            }

            return response()->json($response, $code);
        }

        // ✅ إذا كانت JsonResource (عنصر واحد)
        if ($data instanceof JsonResource) {
            $response['data']  = $data;
            $response['total'] = 1;
            return response()->json($response, $code);
        }

        // ✅ لو Array أو Collection عادية
        if (is_array($data) || $data instanceof \Illuminate\Support\Collection) {
            $response['data']  = $data;
            $response['total'] = is_countable($data) ? count($data) : 0;
            return response()->json($response, $code);
        }

        // ✅ لو نوع غير متوقع
        $response['data'] = $data;
        return response()->json($response, $code);
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
