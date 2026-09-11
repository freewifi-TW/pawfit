<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * users 即 SASD 的 profiles：認證欄位 + 業務欄位放同一張表。
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // 認證
            $table->string('google_id')->nullable()->unique();
            $table->string('email')->unique();
            $table->string('name')->nullable();          // Google 回傳的名稱
            $table->string('google_avatar_url')->nullable();
            $table->rememberToken();
            // 業務
            $table->string('pawfit_id', 20)->nullable()->unique();
            $table->string('display_name', 40)->nullable();
            $table->uuid('avatar_media_id')->nullable();  // FK 於 media 建表後補上
            $table->string('nsfw_pref', 8)->default('hide'); // hide / blur / show
            $table->timestampTz('adult_confirmed_at')->nullable();
            $table->timestampTz('tos_accepted_at')->nullable();
            $table->boolean('is_banned')->default(false);
            $table->timestampTz('last_login_at')->nullable();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
