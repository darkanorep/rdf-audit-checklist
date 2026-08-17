<?php

namespace App\Models;

use App\Filters\CopyFilter;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Guarded([])]
class Copy extends Model
{
    use SoftDeletes;

    protected string $default_filters = CopyFilter::class;

    protected function casts(): array
    {
        return [
            'checklist' => 'array',
            'information' => 'array'
        ];
    }

    public function findings() {
        return $this->hasMany(Finding::class);
    }

    public function responses()
    {
        return $this->hasMany(Response::class, 'copy_id');
    }
}


