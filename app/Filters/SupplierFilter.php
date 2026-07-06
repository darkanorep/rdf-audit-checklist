<?php

declare(strict_types=1);

namespace App\Filters;

use Essa\APIToolKit\Filters\QueryFilters;

class SupplierFilter extends BaseFilter
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
}
