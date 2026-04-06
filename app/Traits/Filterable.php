<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

trait Filterable
{
    protected function applyFilters(Builder $query, Request $request, array $filterableFields = []): Builder
    {
        foreach ($filterableFields as $field) {
            if ($request->has($field)) {
                $value = $request->query($field);

                if ($value === 'true' || $value === 'false') {
                    $query->where($field, $value === 'true');
                } else {
                    $query->where($field, $value);
                }
            }
        }

        if ($request->has('search') && $request->has('search_fields')) {
            $search = $request->query('search');
            $fields = explode(',', $request->query('search_fields'));
            $query->where(function (Builder $q) use ($search, $fields) {
                foreach ($fields as $field) {
                    $q->orWhere(trim($field), 'like', "%{$search}%");
                }
            });
        }

        return $query;
    }

    protected function applySorting(Builder $query, Request $request, string $defaultSort = 'created_at', string $defaultOrder = 'desc'): Builder
    {
        $sort = $request->query('sort', $defaultSort);
        $order = $request->query('order', $defaultOrder);

        return $query->orderBy($sort, $order);
    }
}
