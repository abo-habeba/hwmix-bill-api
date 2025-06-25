<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class PermissionController extends Controller
{
    /**
     * Get all defined permissions as JSON.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        // جلب جميع تعريفات الصلاحيات من ملف config/permissions_keys.php
        $permissionsConfig = config('permissions_keys');

        // يمكننا هنا اختيار إرجاع جميع البيانات أو فقط الجزء الذي تحتاجه الواجهة الأمامية.
        // في هذا المثال، سنرجع المفاتيح فقط كما هي منظمة في الملف.
        // إذا كنت تحتاج فقط لـ 'key' الخاص بكل صلاحية، يمكننا معالجتها هنا.

        // مثال لإرجاع كل شيء:
        return response()->json($permissionsConfig);

        /*
         * // مثال إذا كنت تريد فقط قائمة مسطحة من جميع الـ 'keys':
         */
        // $allPermissionKeys = [];
        // foreach ($permissionsConfig as $entity => $actions) {
        //     foreach ($actions as $actionData) {
        //         if (isset($actionData['key'])) {
        //             $allPermissionKeys[] = $actionData['key'];
        //         }
        //     }
        // }
        // return response()->json($allPermissionKeys);
    }
}
