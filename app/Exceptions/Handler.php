<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Validation\ValidationException;
use Throwable;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;

class Handler extends ExceptionHandler
{
    /**
     * أنواع الاستثناءات التي لا يتم الإبلاغ عنها.
     *
     * @var array<int, class-string<Throwable>>
     */
    protected $dontReport = [
        //
    ];

    /**
     * الحقول التي لا يتم تخزينها في الجلسة عند حدوث خطأ في التحقق.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * تسجيل الاستثناء في السجلات أو الإبلاغ عنه.
     */
    public function report(Throwable $exception): void
    {
        parent::report($exception);
    }

    /**
     * عرض الاستثناء كرد JSON موحد باستخدام api_exception.
     */
    public function render($request, Throwable $e)
    {
        // الرد الموحد في حالة طلب API أو JSON
        if ($request->expectsJson()) {
            // معالجة استثناءات معينة برسائل عربية
            if ($e instanceof AuthenticationException) {
                return api_unauthorized('يجب تسجيل الدخول للوصول إلى هذا المورد');
            }

            if ($e instanceof AuthorizationException) {
                return api_forbidden('ليس لديك صلاحية للوصول إلى هذا المورد');
            }

            if ($e instanceof NotFoundHttpException) {
                return api_not_found('الرابط المطلوب غير موجود');
            }

            if ($e instanceof MethodNotAllowedHttpException) {
                return api_error('طريقة الطلب غير مسموحة', [], 405);
            }

            if ($e instanceof ValidationException) {
                return api_error('خطأ في التحقق من البيانات', $e->errors(), 422);
            }

            if ($e instanceof HttpException) {
                return api_error($e->getMessage(), [], $e->getStatusCode());
            }

            // أي استثناء غير محدد
            return api_exception($e);
        }

        // غير طلبات API هيتعامل معها Laravel بشكل عادي
        return parent::render($request, $e);
    }

    /**
     * تحديد كود الحالة المناسب بناءً على نوع الاستثناء.
     */
    protected function getStatusCodeFromException(Throwable $e): int
    {
        if ($e instanceof HttpException) {
            return $e->getStatusCode();
        }

        if ($e instanceof ValidationException) {
            return 422;
        }

        return 500;
    }
}
