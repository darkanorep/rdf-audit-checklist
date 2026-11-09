<?php

declare(strict_types=1);

namespace App\Filters;

use Essa\APIToolKit\Filters\QueryFilters;

class ChecklistFilter extends BaseFilter
{
    protected array $allowedFilters = ['title'];

    protected array $columnSearch = ['title'];
}
