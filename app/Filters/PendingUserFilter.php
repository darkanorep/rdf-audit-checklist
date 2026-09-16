<?php

declare(strict_types=1);

namespace App\Filters;

use Essa\APIToolKit\Filters\QueryFilters;

class PendingUserFilter extends BaseFilter
{
    protected array $allowedFilters = ['employee_id'];

    protected array $columnSearch = ['employee_id'];
}
