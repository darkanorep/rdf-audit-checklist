<?php

namespace App\Models;

use App\Filters\CategoryTypeFilter;
use Essa\APIToolKit\Filters\Filterable;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Guarded([])]
class CategoryType extends Model
{
    use SoftDeletes, Filterable;

    protected string $default_filters = CategoryTypeFilter::class;
}
