<?php

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

function smart_search_paginated(Collection $items, string $search, array $fields, array $queryParams = [], ?int $threshold = null, int $perPage = 10, int $page = 1): LengthAwarePaginator
{
    $threshold = $threshold ?? (strlen($search) <= 5 ? 70 : 50);
    $similar = [];

    Log::debug('🔍 بدء البحث الذكي بالكلمة: ' . $search);
    Log::debug('📋 عدد العناصر المرشحة للبحث الذكي: ' . $items->count());

    foreach ($items as $item) {
        $maxPercent = 0;

        foreach ($fields as $fieldName) {
            $value = data_get($item, $fieldName);

            if (!$value) continue;

            $words = preg_split('/\s+/', $value);

            foreach ($words as $word) {
                similar_text($search, $word, $percent);
                $maxPercent = max($maxPercent, $percent);
            }

            Log::debug("🔎 تقييم حقل [$fieldName] => [$value] | maxPercent: $maxPercent");
        }

        if ($maxPercent >= $threshold) {
            $item->match_percent = $maxPercent;
            $similar[] = $item;

            Log::debug("✅ تم اختيار العنصر: ", [
                'match_percent' => $maxPercent,
                'fields' => collect($fields)->mapWithKeys(fn($f) => [$f => data_get($item, $f)]),
            ]);
        } else {
            Log::debug("❌ لا يوجد تطابق كافي لهذا العنصر: ", [
                'match_percent' => $maxPercent,
                'fields' => collect($fields)->mapWithKeys(fn($f) => [$f => data_get($item, $f)]),
            ]);
        }
    }

    usort($similar, function ($a, $b) use ($search, $fields) {
        $getMax = function ($item) use ($search, $fields) {
            $max = 0;
            foreach ($fields as $field) {
                $value = data_get($item, $field);
                if (!$value) continue;

                foreach (preg_split('/\s+/', $value) as $word) {
                    similar_text($search, $word, $percent);
                    $max = max($max, $percent);
                }
            }
            return $max;
        };

        return $getMax($b) <=> $getMax($a);
    });

    $results = array_slice($similar, ($page - 1) * $perPage, $perPage);

    Log::debug("✅ عدد النتائج الذكية: " . count($similar));

    return new LengthAwarePaginator(
        $results,
        count($similar),
        $perPage,
        $page,
        ['path' => url()->current(), 'query' => $queryParams]
    );
}
