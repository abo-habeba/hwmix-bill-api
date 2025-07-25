<?php

namespace App\Traits;

use App\Models\Image;
use App\Services\ImageService;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasImages
{
    /**
     * علاقة الصور (Polymorphic)
     */
    public function images(): MorphMany
    {
        return $this->morphMany(Image::class, 'imageable');
    }

    /**
     * علاقة اللوجو (صورة واحدة - Polymorphic)
     */
    public function logo(): MorphOne
    {
        return $this->morphOne(Image::class, 'imageable')->where('type', 'logo');
    }

    /**
     * علاقة الأفاتار (صورة واحدة - Polymorphic)
     */
    public function avatar(): MorphOne
    {
        return $this->morphOne(Image::class, 'imageable')->where('type', 'avatar');
    }

    /**
     * ربط الصور بهذا الكيان
     */
    public function attachImages(array $imageIds, string $type = 'gallery'): void
    {
        ImageService::attachImagesToModel($imageIds, $this, $type);
    }

    /**
     * مزامنة الصور (إضافة الجديدة + حذف المحذوفة)
     */
    public function syncImages(array $imageIds, string $type = 'gallery'): void
    {
        ImageService::syncImagesWithModel($imageIds, $this, $type);
    }

    /**
     * حذف كل الصور المرتبطة
     */
    public function deleteAllImages(): void
    {
        $ids = $this->images->pluck('id')->toArray();
        ImageService::deleteImages($ids);
    }

    /**
     * فك الربط مع كل الصور (بدون حذف الملفات)
     */
    public function detachAllImages(): void
    {
        ImageService::detachImagesFromModel($this);
    }
}
