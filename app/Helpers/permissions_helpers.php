<?php

if (!function_exists('perm_key')) {
    /**
     * Get the full permission key from the permissions registry.
     *
     * @param string $permissionKey  مثال: 'users.view_all'
     * @return string
     */
    function perm_key(string $permissionKey): string
    {
        // تقسيم المفتاح المدخل إلى كيان (entity) وفعل (action)
        list($entity, $action) = explode('.', $permissionKey, 2);  // تحديد 2 لضمان صحة التقسيم

        // جلب جميع الصلاحيات من ملف config/permissions_keys.php
        $permissions = config('permissions_keys');

        // التحقق مما إذا كان الكيان والفعل موجودين في مصفوفة الصلاحيات
        if (isset($permissions[$entity][$action]['key'])) {
            return $permissions[$entity][$action]['key'];
        }

        // إذا لم يتم العثور على المفتاح، يمكنك اختيار رمي استثناء أو إرجاع المفتاح الأصلي
        // إرجاع المفتاح الأصلي يمكن أن يكون مفيدًا للتصحيح أو إذا كنت تتوقع مفاتيح غير معرفة أحيانًا.
        // ولكن يفضل رمي استثناء لتجنب الأخطاء الصامتة.
        throw new \InvalidArgumentException("Permission key '{$permissionKey}' not found in permissions registry.");
    }
}
