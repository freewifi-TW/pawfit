<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('reporter_id')->constrained('users')->cascadeOnDelete();
            $table->string('target_type', 10);   // media / fursona / profile
            $table->uuid('target_id');
            $table->string('reason_code', 16);   // illegal / untagged_nsfw / copyright / harassment / other
            $table->text('detail')->nullable();
            $table->string('status', 10)->default('open'); // open / resolved / dismissed
            $table->foreignUuid('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('resolved_at')->nullable();
            $table->timestampsTz();

            $table->index(['status', 'created_at']);
            $table->index(['target_type', 'target_id']);
        });

        Schema::create('admin_actions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('admin_id')->constrained('users')->cascadeOnDelete();
            $table->string('action', 24);        // remove_media / purge_media / remove_fursona / ban_user / ...
            $table->string('target_type', 10);
            $table->uuid('target_id');
            $table->text('note')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_actions');
        Schema::dropIfExists('reports');
    }
};
