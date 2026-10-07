<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Media extends Model
{
    use HasFactory, HasUuids;

    public const KINDS = ['art2d', 'model3d', 'photo'];

    public const STATUSES = ['processing', 'active', 'removed', 'failed'];

    /** 來源：一般上傳／換獸頭貼圖工具輸出（Phase 2 FR-B8.4）；Phase 5 再增列 ai_* */
    public const ORIGINS = ['upload', 'head_sticker'];

    protected $table = 'media';

    protected $fillable = [
        'fursona_id', 'owner_id', 'kind', 'storage_key', 'display_key', 'thumb_key',
        'watermarked_key', 'mime', 'width', 'height', 'bytes', 'credit_name', 'credit_url',
        'is_nsfw', 'visibility_override', 'caption', 'sort_order', 'status', 'status_note',
        'origin', 'is_head_sticker',
    ];

    protected function casts(): array
    {
        return [
            'is_nsfw' => 'boolean',
            'is_head_sticker' => 'boolean',
            'width' => 'integer',
            'height' => 'integer',
            'bytes' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function fursona(): BelongsTo
    {
        return $this->belongsTo(Fursona::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /** 實際生效的隱私：單圖覆寫優先，否則繼承獸設。 */
    public function effectiveVisibility(): string
    {
        return $this->visibility_override ?? $this->fursona->visibility;
    }

    /** 內容是否屬 NSFW：單圖標記或整隻獸設標記皆算。 */
    public function isNsfwContent(): bool
    {
        return $this->is_nsfw || $this->fursona->is_nsfw;
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /** 所有存在 R2/MinIO 上的 object key。 */
    public function allKeys(): array
    {
        return array_values(array_filter([
            $this->storage_key, $this->display_key, $this->thumb_key, $this->watermarked_key,
        ]));
    }
}
