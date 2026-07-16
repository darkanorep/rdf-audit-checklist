<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Guarded([])]
class Copy extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'checklist' => 'array',
            'information' => 'array'
        ];
    }
}


