<?php

namespace App\Http\Resources\InvoiceItem;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\ProductVariant\ProductVariantResource;
use App\Http\Resources\Product\ProductResource; // إضافة مورد المنتج

class InvoiceItemResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'variant_id' => $this->variant_id,
            'invoice_id' => $this->invoice_id,
            'product_id' => $this->product_id,
            'name' => $this->name,
            'quantity' => number_format($this->quantity, 2, '.', ''), // تنسيق الكمية
            'unit_price' => number_format($this->unit_price, 2, '.', ''),
            'cost_price' => number_format($this->cost_price, 2, '.', ''), // إضافة سعر التكلفة
            'discount' => number_format($this->discount, 2, '.', ''),
            'total' => number_format($this->total, 2, '.', ''),

            'variant' => new ProductVariantResource($this->whenLoaded('variant')),
            'product' => new ProductResource($this->whenLoaded('product')), // تحميل المنتج كمورد

            'created_at' => $this->created_at ? $this->created_at->format('Y-m-d H:i:s') : null,
            'updated_at' => $this->updated_at ? $this->updated_at->format('Y-m-d H:i:s') : null,
        ];
    }
}
