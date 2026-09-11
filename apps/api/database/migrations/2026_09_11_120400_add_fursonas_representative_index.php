<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * 每個用戶至多一隻代表獸設（partial unique index）。
     *
     * 放在所有會 ALTER fursonas 的 migration 之後：sqlite 不支援 ALTER ADD FOREIGN KEY，
     * Laravel 會重建資料表並依 pragma 重建索引，partial index 的 WHERE 條件會在重建時遺失。
     */
    public function up(): void
    {
        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS fursonas_one_representative_per_owner ON fursonas (owner_id) WHERE is_representative');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS fursonas_one_representative_per_owner');
    }
};
