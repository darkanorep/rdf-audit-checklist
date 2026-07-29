<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;

#[Guarded([])]
class Image extends Model
{
    protected function casts(): array
    {
        return [
            'url' => 'array'
        ];
    }
}
