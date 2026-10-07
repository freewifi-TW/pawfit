<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Report extends Model
{
    use HasUuids;

    public const TARGET_TYPES = ['media', 'fursona', 'profile', 'post', 'comment']; // post/comment：Phase 2

    /** FR-B7.1：同一目標累積多少筆（不同檢舉者的）未處理檢舉時自動降能見度 */
    public const SUPPRESS_THRESHOLD = 3;

    public const REASONS = ['illegal', 'untagged_nsfw', 'copyright', 'harassment', 'other'];

    public const STATUSES = ['open', 'resolved', 'dismissed'];

    protected $fillable = [
        'reporter_id', 'target_type', 'target_id', 'reason_code', 'detail',
        'status', 'resolved_by', 'resolved_at',
    ];

    protected function casts(): array
    {
        return ['resolved_at' => 'datetime'];
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
