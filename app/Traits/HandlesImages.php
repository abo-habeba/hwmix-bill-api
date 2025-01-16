<?php

namespace App\Traits;

use App\Models\Image;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

trait HandlesImages
{
    public function saveImage($type, $file)
    {
        if (!$file) {
            return null;
        }
        DB::beginTransaction();
        try {
            $modelName = Str::snake(class_basename($this));
            $folder = "{$modelName}/{$type}";
            $fileName = "{$modelName}_{$this->id}_" . uniqid() . '.' . $file->getClientOriginalExtension();
            $url = $file->storeAs($folder, $fileName, 'public');

            $image = $this->images()->create([
                'url' => $url,
                'type' => $type,
            ]);

            DB::commit();

            return $image;
        } catch (\Exception $e) {
            DB::rollBack();
            if (isset($url) && Storage::disk('public')->exists($url)) {
                Storage::disk('public')->delete($url);
            }
            throw $e;
        }
    }

    public function deleteImage(Image $image)
    {
        DB::beginTransaction();

        try {
            $imageDeletedFromDisk = false;
            if (Storage::disk('public')->exists($image->url)) {
                $imageDeletedFromDisk = Storage::disk('public')->delete($image->url);
            }

            $imageDeletedFromDb = $image->delete();

            if ($imageDeletedFromDisk && !$imageDeletedFromDb) {
                Storage::disk('public')->put($image->url, file_get_contents(storage_path('app/public/' . $image->url)));
                DB::rollBack();
                throw new \Exception("فشل الحذف من قاعدة البيانات بعد حذف الصورة.");
            }

            DB::commit();
            return $imageDeletedFromDb;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function deleteAllImages()
    {
        DB::beginTransaction();

        try {
            $this->images->each(function ($image) {
                $this->deleteImage($image);
            });

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
