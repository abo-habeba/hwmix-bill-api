<?php

namespace App\Http\Requests\Product;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules(): array
    {
        /** @var \App\Models\Product|int|null $product */
        $product = $this->route('product');
        $productId = is_object($product) ? $product->id : $product;

        return [
            // — معلومات أساسية للمنتج
            'name' => 'sometimes|required|string|max:255',
            'slug' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('products', 'slug')->ignore($productId),
            ],
            'published_at' => 'nullable|date',
            'desc' => 'nullable|string',
            'desc_long' => 'nullable|string',
            'category_id' => 'sometimes|required|exists:categories,id',
            'brand_id' => 'nullable|exists:brands,id',
            'company_id' => 'nullable|exists:companies,id',
            'created_by' => 'nullable|exists:users,id',
            // — المتغيرات (variants)
            'variants' => 'required|array|min:1',
            'variants.*.id' => 'sometimes|exists:product_variants,id',
            'variants.*.barcode' => [
                'nullable',
                'string',
                'max:255',
                // بنستخدم Rule::unique مع ignore لكل variant لو اتبعت الـ id بتاعه
                function ($attribute, $value, $fail) {
                    $idx = explode('.', $attribute)[1];  // position داخل array
                    $variantId = $this->input("variants.$idx.id");
                    $rule = Rule::unique('product_variants', 'barcode');
                    if ($variantId) {
                        $rule->ignore($variantId);
                    }
                    if (!is_null($value) && !app('validator')->make(
                        [$attribute => $value],
                        [$attribute => [$rule]]
                    )->passes()) {
                        $fail(__('validation.unique', ['attribute' => $attribute]));
                    }
                },
            ],
            'variants.*.sku' => [
                'nullable',
                'string',
                'max:255',
                function ($attribute, $value, $fail) {
                    $idx = explode('.', $attribute)[1];
                    $variantId = $this->input("variants.$idx.id");
                    $rule = Rule::unique('product_variants', 'sku');
                    if ($variantId) {
                        $rule->ignore($variantId);
                    }
                    if (!is_null($value) && !app('validator')->make(
                        [$attribute => $value],
                        [$attribute => [$rule]]
                    )->passes()) {
                        $fail(__('validation.unique', ['attribute' => $attribute]));
                    }
                },
            ],
            'variants.*.retail_price' => 'nullable|numeric|min:0',
            'variants.*.wholesale_price' => 'nullable|numeric|min:0',
            'variants.*.profit_margin' => 'nullable|numeric|min:0|max:100',
            'variants.*.image' => 'nullable|string|max:255',
            'variants.*.weight' => 'nullable|numeric|min:0',
            'variants.*.dimensions' => 'nullable|string|max:255',
            'variants.*.tax' => 'nullable|numeric|min:0|max:100',
            'variants.*.discount' => 'nullable|numeric|min:0',
            'variants.*.status' => 'nullable|in:active,inactive,discontinued',
            'variants.*.created_by' => 'nullable|exists:users,id',
            'variants.*.company_id' => 'nullable|exists:companies,id',
            // — خصائص المتغير
            'variants.*.attributes' => 'nullable|array',
            'variants.*.attributes.*.id' => 'sometimes|exists:attribute_product_variant,id',
            'variants.*.attributes.*.attribute_id' => 'nullable|exists:attributes,id',
            'variants.*.attributes.*.attribute_value_id' => 'nullable|exists:attribute_values,id',
            // — مخزون المتغير
            'variants.*.stock' => 'required|array',
            'variants.*.stock.id' => 'sometimes|exists:stocks,id',
            'variants.*.stock.qty' => 'nullable|integer|min:0',
            'variants.*.stock.reserved' => 'nullable|integer|min:0',
            'variants.*.stock.expiry' => 'nullable|date',
            'variants.*.stock.status' => 'nullable|in:available,unavailable,expired',
            'variants.*.stock.batch' => 'nullable|string|max:255',
            'variants.*.stock.cost' => 'nullable|numeric|min:0',
            'variants.*.stock.loc' => 'nullable|string|max:255',
            'variants.*.stock.warehouse_id' => 'nullable|exists:warehouses,id',
            'variants.*.stock.company_id' => 'nullable|exists:companies,id',
            'variants.*.stock.created_by' => 'nullable|exists:users,id',
            'variants.*.stock.updated_by' => 'nullable|exists:users,id',
        ];
    }
}
