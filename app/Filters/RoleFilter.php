<?php

declare(strict_types=1);

namespace App\Filters;

use Essa\APIToolKit\Filters\QueryFilters;

class RoleFilter extends QueryFilters
{
    protected array $allowedFilters = ['name'];

    protected array $columnSearch = ['name'];
}
