<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2 M5 換獸頭貼圖（SASD Phase 2 FR-B8）。
 * - media.origin：upload | head_sticker（Phase 5 再增列 ai_*）
 * - media.is_head_sticker：標記為「頭像貼圖素材」（只允許 kind=art2d 且 SFW）
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->string('origin', 24)->default('upload')->after('kind');
            $table->boolean('is_head_sticker')->default(false)->after('is_nsfw');
            $table->index(['owner_id', 'is_head_sticker']);
        });
    }

    public function down(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->dropIndex(['owner_id', 'is_head_sticker']);
            $table->dropColumn(['origin', 'is_head_sticker']);
        });
    }
};
