<?php

namespace App\Http\Resources\Category;

use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    public function toArray($request)
    {
         return [
            'id'          => $this->id,
            'company_id'  => $this->company_id,
            'created_by'  => $this->created_by,
            'name'        => $this->name,
            'description' => $this->description,
            'parent_id'   => $this->parent_id,
            'children'    => CategoryResource::collection($this->whenLoaded('children')),
            'created_at'  => $this->created_at,
            'updated_at'  => $this->updated_at,
         ];
    }
}
