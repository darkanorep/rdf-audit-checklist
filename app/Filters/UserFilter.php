<?php

declare(strict_types=1);

namespace App\Filters;

use Essa\APIToolKit\Filters\QueryFilters;

class UserFilter extends QueryFilters
{
    protected array $allowedFilters = [
        'employee_id',
        'first_name',
        'last_name',
        'username'
    ];

    protected array $columnSearch = [
        'employee_id',
        'first_name',
        'last_name',
        'username'
    ];
}
