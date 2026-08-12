<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Staudenmeir\EloquentJsonRelations\HasJsonRelationships;

#[Guarded([])]
class Finding extends Model
{
    use HasJsonRelationships;
    protected function casts(): array
    {
        return [
            'context' => 'array',
            'observers' => 'json'
        ];
    }

    public function observers() {
        return $this->belongsToJson(User::class, 'observers');
    }

    public function observerUsers()
    {
        return $this->belongsToJson(User::class, 'observers');
    }
}
