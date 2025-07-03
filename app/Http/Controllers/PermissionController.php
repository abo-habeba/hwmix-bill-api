<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Throwable;

class PermissionController extends Controller
{
    /**
     * عرض جميع تعريفات الصلاحيات كـ JSON.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();

            if (!$authUser->hasPermissionTo(perm_key('admin.super')) && !$authUser->hasAnyPermission([perm_key('permissions.view_all'), perm_key('admin.company')])) {
                return api_forbidden('ليس لديك إذن لعرض الصلاحيات.');
            }

            // جلب جميع تعريفات الصلاحيات من ملف config/permissions_keys.php
            $permissionsConfig = config('permissions_keys');

            // يمكنك اختيار إرجاع جميع البيانات أو فقط الجزء الذي تحتاجه الواجهة الأمامية.
            // هنا سنرجع جميع البيانات كما هي منظمة في الملف.
            if (empty($permissionsConfig)) {
                return api_success($permissionsConfig, 'لم يتم العثور على تعريفات صلاحيات.');
            } else {
                return api_success($permissionsConfig, 'تم جلب تعريفات الصلاحيات بنجاح.');
            }
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }
}
