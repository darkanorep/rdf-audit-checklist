<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Guarded([])]
class Role extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'permissions' => 'array'
        ];
    }
}
