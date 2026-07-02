<?php

namespace App\Models;

use App\Filters\RoleFilter;
use Essa\APIToolKit\Filters\Filterable;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Guarded([])]
class Role extends Model
{
    use SoftDeletes, Filterable;

    protected string $default_filters = RoleFilter::class;
    protected function casts(): array
    {
        return [
            'permissions' => 'array'
        ];
    }

    const string ADMIN = 'Admin';
}
