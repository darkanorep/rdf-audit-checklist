<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
//    public function up(): void
//    {
//        Schema::table('copies', function (Blueprint $table) {
//            $table->json('checklist_user_ids')
//                ->storedAs('JSON_EXTRACT(checklist, "$[*].user_id")')
//                ->nullable();
//        });
//
//        DB::statement('ALTER TABLE copies ADD INDEX idx_checklist_user_ids ((CAST(checklist_user_ids AS UNSIGNED ARRAY)))');
//    }
//
//    /**
//     * Reverse the migrations.
//     */
//    public function down(): void
//    {
//        DB::statement('ALTER TABLE copies DROP INDEX idx_checklist_user_ids');
//        Schema::table('copies', function (Blueprint $table) {
//            $table->dropColumn('checklist_user_ids');
//        });
//    }

    public function up(): void
    {
        Schema::table('copies', function (Blueprint $table) {
            $table->json('checklist_user_ids')
                ->storedAs('JSON_EXTRACT(checklist, "$[*].user_id")')
                ->nullable();
        });

        // Note: MariaDB has no multi-valued index support (that's MySQL 8.0.17+
        // only), so JSON_CONTAINS() against this column will do a table scan
        // rather than an index seek. Acceptable for small/medium `copies`
        // tables; revisit with a copy_user pivot table (see migration notes)
        // if this table grows large or the scan shows up in slow query logs.
    }

    public function down(): void
    {
        Schema::table('copies', function (Blueprint $table) {
            $table->dropColumn('checklist_user_ids');
        });
    }
};
