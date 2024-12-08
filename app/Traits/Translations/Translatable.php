<?php

namespace App\Traits\Translations;

trait Translatable
{
    public function getTrans($field)
    {
        $locale = request()->header('Accept-Language', 'ar');
        return $this->translations()->where('locale', $locale)->where('field', $field)->value('value');
    }
}
