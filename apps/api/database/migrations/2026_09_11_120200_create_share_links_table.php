<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('share_links', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('fursona_id')->constrained('fursonas')->cascadeOnDelete();
            $table->string('slug', 16)->unique();       // nanoid(10)
            $table->boolean('watermark')->default(false);
            $table->timestampTz('revoked_at')->nullable();
            $table->timestampsTz();

            $table->index(['fursona_id', 'revoked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('share_links');
    }
};
