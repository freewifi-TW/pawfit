<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('fursona_id')->constrained('fursonas')->cascadeOnDelete();
            $table->foreignUuid('owner_id')->constrained('users')->cascadeOnDelete(); // 反正規化，方便授權查詢
            $table->string('kind', 10);                 // art2d / model3d / photo
            $table->string('storage_key');              // 原檔
            $table->string('display_key')->nullable();  // 展示版（長邊 ≤ 2048）
            $table->string('thumb_key')->nullable();    // 縮圖（長邊 ≤ 512，webp）
            $table->string('watermarked_key')->nullable();
            $table->string('mime', 40)->nullable();
            $table->unsignedInteger('width')->nullable();   // 展示版尺寸
            $table->unsignedInteger('height')->nullable();
            $table->unsignedBigInteger('bytes')->default(0); // 原檔大小，計入配額
            $table->string('credit_name', 80)->nullable();
            $table->string('credit_url', 300)->nullable();
            $table->boolean('is_nsfw');                 // 上傳時強制選擇
            $table->string('visibility_override', 12)->nullable(); // 空 = 繼承獸設
            $table->string('caption', 200)->nullable();
            $table->integer('sort_order')->default(0);
            $table->string('status', 12)->default('processing'); // processing / active / removed / failed
            $table->text('status_note')->nullable();    // 失敗原因或下架備註
            $table->timestampsTz();

            $table->index(['fursona_id', 'sort_order']);
            $table->index(['owner_id', 'created_at']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreign('avatar_media_id')->references('id')->on('media')->nullOnDelete();
        });
        Schema::table('fursonas', function (Blueprint $table) {
            $table->foreign('avatar_media_id')->references('id')->on('media')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropForeign(['avatar_media_id']));
        Schema::table('fursonas', fn (Blueprint $t) => $t->dropForeign(['avatar_media_id']));
        Schema::dropIfExists('media');
    }
};
