<?php

namespace App\Models;

use App\Filters\PendingUserFilter;
use Essa\APIToolKit\Filters\Filterable;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Guarded([])]
class PendingUser extends Model
{
    use SoftDeletes, Filterable;

    protected string $default_filters = PendingUserFilter::class;
}
