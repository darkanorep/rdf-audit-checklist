<?php

namespace App\Services;

use App\Models\Finding;
use Illuminate\Support\Facades\DB;

class FindingService
{
//    public function getFindings()
//    {
//        return Finding::with(['observers' => function ($query) {
//            $query->select('users.id', DB::raw("CONCAT(first_name, ' ', last_name) as full_name"));
//        }])->get();
//    }
    public function storeFindings($data) {
        return Finding::create($data);
    }
}
