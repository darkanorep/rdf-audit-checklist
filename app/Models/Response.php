<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Guarded([])]
class Response extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'content' => 'array'
        ];
    }

    public function images() {
        return $this->hasMany(Image::class);
    }
}
