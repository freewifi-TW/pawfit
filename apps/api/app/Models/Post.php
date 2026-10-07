<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** 貼文（Phase 2 FR-B3）：1–10 張圖庫圖＋文字＋標籤；隱私二段 public / friends。 */
class Post extends Model
{
    use HasFactory, HasUuids;

    public const VISIBILITIES = ['public', 'friends'];

    public const STATUSES = ['active', 'removed', 'suppressed'];

    public const MAX_MEDIA = 10;

    protected $fillable = [
        'author_id', 'fursona_id', 'body', 'tags', 'is_nsfw', 'visibility', 'status', 'status_note',
        'like_count', 'comment_count',
    ];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'is_nsfw' => 'boolean',
            'like_count' => 'integer',
            'comment_count' => 'integer',
        ];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function fursona(): BelongsTo
    {
        return $this->belongsTo(Fursona::class);
    }

    /** 貼文的圖，依 post_media.sort。 */
    public function media(): BelongsToMany
    {
        return $this->belongsToMany(Media::class, 'post_media')->withPivot('sort')->orderBy('post_media.sort');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(PostComment::class);
    }

    public function likers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'post_likes')->withPivot('created_at');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('status', 'active');
    }

    /** 媒體 id 是否在這篇貼文裡（圖片出口 p= 用）。 */
    public function includesMedia(string $mediaId): bool
    {
        return $this->media()->where('media.id', $mediaId)->exists();
    }

    public function url(): string
    {
        return config('pawfit.frontend_url').'/post/'.$this->id;
    }
}
