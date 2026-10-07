<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2 M1 好友與封鎖（SASD Phase 2 FR-B1、§2.1）。全部 additive。
 * friendships 正規化為 user_a < user_b（字串比較），一組關係只有一列；requested_by 記錄誰發出邀請。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('friendships', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_a')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('user_b')->constrained('users')->cascadeOnDelete();
            $table->string('status', 16)->default('pending'); // pending | accepted
            $table->foreignUuid('requested_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('accepted_at')->nullable();

            $table->unique(['user_a', 'user_b']);
            $table->index(['user_b', 'status']);
            $table->index(['user_a', 'status']);
        });

        Schema::create('blocks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('blocker_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('blocked_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->unique(['blocker_id', 'blocked_id']);
            $table->index('blocked_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blocks');
        Schema::dropIfExists('friendships');
    }
};
