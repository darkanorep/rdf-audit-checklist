<?php

declare(strict_types=1);

namespace App\Filters;

use Essa\APIToolKit\Filters\QueryFilters;

class CategoryTypeFilter extends BaseFilter
{
    protected array $allowedFilters = ['name'];

    protected array $columnSearch = ['name'];
}
