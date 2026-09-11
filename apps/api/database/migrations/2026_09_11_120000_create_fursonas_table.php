<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fursonas', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('name', 60);
            $table->string('species', 60)->nullable();
            $table->text('bio')->nullable();
            $table->jsonb('tags')->default('[]');      // ["犬科", "赤狐"]
            $table->jsonb('palette')->default('[]');   // [{hex, name, note, sort}]
            $table->string('visibility', 12)->default('public'); // public / unlisted / private（Phase 2 增列 friends）
            $table->boolean('is_nsfw')->default(false);
            $table->boolean('is_representative')->default(false);
            $table->uuid('avatar_media_id')->nullable(); // 獸設頭像（圖庫中的一張），FK 於 media 建表後補上
            $table->timestampTz('removed_at')->nullable(); // 管理員下架（隱藏但保留資料與 R2 物件供申訴）
            $table->timestampsTz();

            $table->index(['owner_id', 'visibility']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fursonas');
    }
};
