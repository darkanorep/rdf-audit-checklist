<?php

namespace App\Models;

use App\Filters\ChecklistFilter;
use Essa\APIToolKit\Filters\Filterable;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Guarded([])]
class Checklist extends Model
{
    use SoftDeletes, Filterable;
    protected function casts(): array
    {
        return [
            'checklist' => 'array'
        ];
    }

    protected string $default_filters = ChecklistFilter::class;
}
