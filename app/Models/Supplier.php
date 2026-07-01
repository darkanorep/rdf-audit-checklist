<?php

namespace App\Models;

use App\Filters\SupplierFilter;
use Essa\APIToolKit\Filters\Filterable;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Guarded([])]
class Supplier extends Model
{
    use SoftDeletes, Filterable;

    protected string $default_filters = SupplierFilter::class;
    protected function casts(): array
    {
        return [
            'contact_person' => 'array',
            'contact_no' => 'array',
            'products_offered' => 'array',
        ];
    }
}
