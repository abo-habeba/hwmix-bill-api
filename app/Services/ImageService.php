<?php

namespace App\Services;

use App\Models\Image;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class ImageService
{
    /**
     * ربط مجموعة صور بكيان محدد (منتج، متغير، مستخدم...)
     */
    public static function attachImagesToModel(array $imageIds, Model $model, string $type = 'gallery'): void
    {
        $user = Auth::user();
        if (!$user) return;

        $companyId = $user->company_id;
        $modelName = Str::snake(class_basename($model));
        $storageBase = "uploads/{$companyId}/{$modelName}/{$type}"; // مسار التخزين داخل القرص العام

        foreach ($imageIds as $imageId) {
            $image = Image::where('id', $imageId)
                ->where('created_by', $user->id)
                ->first();

            if (!$image) continue;

            // استخراج المسار النسبي من URL المخزن
            $oldRelativePath = str_replace('storage/', '', $image->url);
            $ext = pathinfo($image->url, PATHINFO_EXTENSION);

            $fileName = "{$modelName}_{$model->id}_" . uniqid() . '.' . $ext;
            $newRelativePath = "{$storageBase}/{$fileName}"; // المسار النسبي الجديد

            // نقل الملف باستخدام قرص public
            if (Storage::disk('public')->exists($oldRelativePath)) {
                Storage::disk('public')->move($oldRelativePath, $newRelativePath);
            }

            $image->update([
                'url' => Storage::url($newRelativePath), // تحديث URL العام الجديد
                'imageable_id' => $model->id,
                'imageable_type' => get_class($model),
                'type' => $type,
            ]);
        }
    }

    /**
     * حذف مجموعة صور من التخزين وقاعدة البيانات
     */
    public static function deleteImages(array $imageIds): void
    {
        $images = Image::whereIn('id', $imageIds)->get();

        foreach ($images as $image) {
            // استخراج المسار النسبي من URL المخزن للحذف
            $relativePathToDelete = str_replace('storage/', '', $image->url);

            if (Storage::disk('public')->exists($relativePathToDelete)) {
                Storage::disk('public')->delete($relativePathToDelete);
            }

            $image->delete();
        }
    }

    /**
     * فك الربط بين الصور وكيان معين بدون حذفها
     */
    public static function detachImagesFromModel(Model $model): void
    {
        $images = Image::where('imageable_type', get_class($model))
            ->where('imageable_id', $model->id)
            ->get();

        foreach ($images as $image) {
            $image->update([
                'imageable_type' => null,
                'imageable_id' => null,
            ]);
        }
    }

    /**
     * مزامنة الصور: حذف الصور القديمة، ربط الصور الجديدة فقط
     */
    public static function syncImagesWithModel(array $newImageIds, Model $model, string $type = 'gallery'): void
    {
        $user = Auth::user();
        if (!$user) return;

        $companyId = $user->company_id;
        $modelClass = get_class($model);
        $modelName = Str::snake(class_basename($model));
        $storageBase = "uploads/{$companyId}/{$modelName}/{$type}";

        // الصور المرتبطة حاليًا
        $currentImages = Image::where('imageable_type', $modelClass)
            ->where('imageable_id', $model->id)
            ->get();

        $currentImageIds = $currentImages->pluck('id')->toArray();

        // حذف الصور اللي مش موجودة في القائمة الجديدة
        $toDelete = array_diff($currentImageIds, $newImageIds);
        self::deleteImages($toDelete);

        // إضافة الصور الجديدة فقط
        $toAttach = array_diff($newImageIds, $currentImageIds);

        foreach ($toAttach as $imageId) {
            $image = Image::where('id', $imageId)
                ->where('created_by', $user->id)
                ->first();

            if (!$image) continue;

            // استخراج المسار النسبي من URL المخزن
            $oldRelativePath = str_replace('storage/', '', $image->url);
            $ext = pathinfo($image->url, PATHINFO_EXTENSION);

            $fileName = "{$modelName}_{$model->id}_" . uniqid() . '.' . $ext;
            $newRelativePath = "{$storageBase}/{$fileName}"; // المسار النسبي الجديد

            // نقل الملف باستخدام قرص public
            if (Storage::disk('public')->exists($oldRelativePath)) {
                Storage::disk('public')->move($oldRelativePath, $newRelativePath);
            }

            $image->update([
                'url' => Storage::url($newRelativePath), // تحديث URL العام الجديد
                'imageable_id' => $model->id,
                'imageable_type' => $modelClass,
                'type' => $type,
            ]);
        }
    }
}
