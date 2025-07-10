<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // أو حسب صلاحياتك
    }

    public function rules(): array
    {
        return [
            'file' => 'required|image|max:2048',
            'type' => 'required|string', // avatar, cover, gallery, logo, etc.
            'imageable_type' => 'required|string', // مثلا: App\Models\Product
            'imageable_id' => 'required|integer',
        ];
    }
}
