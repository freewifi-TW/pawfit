<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2 M2–M4 發文、互動（SASD Phase 2 §2.1）。全部 additive。
 * tags 用 json（Postgres 上為 jsonb + GIN，查詢走 whereJsonContains，sqlite 測試也能跑）。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('author_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('fursona_id')->nullable()->constrained('fursonas')->nullOnDelete();
            $table->text('body');
            $table->json('tags');
            $table->boolean('is_nsfw')->default(false);
            $table->string('visibility', 16)->default('public'); // public | friends
            $table->string('status', 16)->default('active'); // active | removed | suppressed
            $table->string('status_note')->nullable();
            $table->unsignedInteger('like_count')->default(0);
            $table->unsignedInteger('comment_count')->default(0);
            $table->timestamps();

            $table->index(['author_id', 'created_at']);
            $table->index(['visibility', 'status', 'created_at']);
        });
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE INDEX posts_tags_gin ON posts USING GIN ((tags::jsonb))');
        }

        Schema::create('post_media', function (Blueprint $table) {
            $table->foreignUuid('post_id')->constrained('posts')->cascadeOnDelete();
            $table->foreignUuid('media_id')->constrained('media')->cascadeOnDelete();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->primary(['post_id', 'media_id']);
        });

        Schema::create('post_likes', function (Blueprint $table) {
            $table->foreignUuid('post_id')->constrained('posts')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('created_at')->nullable();
            $table->primary(['post_id', 'user_id']);
        });

        Schema::create('post_comments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('post_id')->constrained('posts')->cascadeOnDelete();
            $table->foreignUuid('author_id')->constrained('users')->cascadeOnDelete();
            $table->string('body', 500);
            $table->string('status', 16)->default('active'); // active | removed
            $table->timestamps();

            $table->index(['post_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_comments');
        Schema::dropIfExists('post_likes');
        Schema::dropIfExists('post_media');
        Schema::dropIfExists('posts');
    }
};
