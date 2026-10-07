<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M7 委託需求單（SASD Phase 1 FR-6、§2.2 commission_kits）。
 * 需求單是「建立當下的資料快照」＋分享連結，之後修改獸設不影響既有需求單（§2.4 第 5 點）。
 * kind / size_card_id / pin_set 預留給 Phase 4 毛裝需求包，只做 additive migration。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commission_kits', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('fursona_id')->constrained('fursonas')->cascadeOnDelete();
            $table->foreignUuid('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('kind', 16)->default('art2d'); // art2d | fursuit（Phase 4）
            $table->string('slug', 16)->unique();
            $table->json('snapshot'); // 角色資料、參考圖 caption/credit、本次要求
            $table->json('media_ids'); // 參考圖 uuid[]，依使用者勾選順序
            $table->json('brief_text'); // {"zh-TW": "...", "en": "..."}
            $table->string('brief_source', 16)->default('template'); // template | llm
            $table->string('sheet_key')->nullable(); // 合成圖 R2 key；queue 生成中為 null
            $table->boolean('is_nsfw')->default(false);
            $table->uuid('size_card_id')->nullable(); // Phase 4
            $table->json('pin_set')->nullable(); // Phase 4
            $table->string('status', 16)->default('processing'); // processing | active | failed | revoked
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['owner_id', 'created_at']);
            $table->index(['fursona_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commission_kits');
    }
};
