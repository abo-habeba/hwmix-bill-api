<?php

namespace App\Http\Resources\Image;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ImageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'url' => asset($this->url),
            'file_name' => $this->file_name,
            'mime_type' => $this->mime_type,
            'size' => $this->size,
            'type' => $this->type,
            'is_temp' => (bool) $this->is_temp,
            'imageable_id' => $this->imageable_id,
            'imageable_type' => $this->imageable_type,
            'company_id' => $this->company_id,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at->toDateTimeString(),
        ];
    }
}
