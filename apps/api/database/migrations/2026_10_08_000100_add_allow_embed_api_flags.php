<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M6 開放與嵌入（SASD Phase 1 FR-7.1）：
 * - users.allow_embed_api：總開關，預設 false（opt-in，§2.4 第 6 點）
 * - fursonas.allow_embed_api：單隻覆寫，null＝繼承用戶設定
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('allow_embed_api')->default(false)->after('is_banned');
        });
        Schema::table('fursonas', function (Blueprint $table) {
            $table->boolean('allow_embed_api')->nullable()->after('is_representative');
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('allow_embed_api'));
        Schema::table('fursonas', fn (Blueprint $table) => $table->dropColumn('allow_embed_api'));
    }
};
