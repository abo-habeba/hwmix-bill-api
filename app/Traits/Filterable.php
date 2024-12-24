<?php
namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;

trait Filterable
{
    /**
     * Apply filters to the query, including date range filters and search filters.
     *
     * @param Builder $query
     * @param array $filters
     * @return Builder
     */
    public function scopeFilter(Builder $query, array $filters): Builder
    {
        // إزالة الفلاتر التي لا تحتوي على قيمة
        $filters = array_filter($filters, function ($value) {
            return !is_null($value) && $value !== '';
        });

        // تطبيق فلاتر التاريخ إذا كانت موجودة
        if (isset($filters['created_at']) && is_array($filters['created_at'])) {
            if (isset($filters['created_at']['start']) && isset($filters['created_at']['end'])) {
                $query->whereBetween('created_at', [
                    $filters['created_at']['start'],
                    $filters['created_at']['end']
                ]);
            }
        }

        // تطبيق فلاتر البحث (LIKE) على الحقول الأخرى
        foreach ($filters as $field => $value) {
            // إذا كانت الحقل عبارة عن تواريخ يمكن التعامل معها بشكل مختلف
            if ($field != 'created_at' && !is_array($value)) {
                $query->where($field, 'LIKE', '%' . $value . '%');
            }
        }

        return $query;
    }
}
