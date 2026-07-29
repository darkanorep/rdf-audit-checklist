<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('copies', function (Blueprint $table) {
            $table->json('checklist_user_ids')
                ->storedAs('JSON_EXTRACT(checklist, "$[*].user_id")')
                ->nullable();
        });

        DB::statement('ALTER TABLE copies ADD INDEX idx_checklist_user_ids ((CAST(checklist_user_ids AS UNSIGNED ARRAY)))');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE copies DROP INDEX idx_checklist_user_ids');
        Schema::table('copies', function (Blueprint $table) {
            $table->dropColumn('checklist_user_ids');
        });
    }
};
