<?php

declare(strict_types=1);

namespace App\Filters;

use Essa\APIToolKit\Filters\QueryFilters;

class SupplierFilter extends QueryFilters
{
    protected array $allowedFilters = [
        'name',
        'contact_person',
        'address',
        'tin_no',
        'contact_no',
        'products_offered',
        'email',
        'remarks'
    ];

    protected array $columnSearch = [
        'name',
        'contact_person',
        'address',
        'tin_no',
        'contact_no',
        'products_offered',
        'email',
        'remarks'
    ];

    public function status($status) {
        return $this->builder->withTrashed()->when(!$status, function ($query) {
            $query->whereNotNull('deleted_at');
        }, function ($query) use ($status) {
            $query->when($status, function ($query){
                $query->whereNull('deleted_at');
            });
        });
    }
}
