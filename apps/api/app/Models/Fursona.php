<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Fursona extends Model
{
    use HasFactory, HasUuids;

    public const VISIBILITIES = ['public', 'unlisted', 'private', 'friends'];

    /** 目前可選的隱私值：friends 只在 Phase 2 好友功能啟用時開放（既有資料不自動變更，FR-B2.3）。 */
    public static function allowedVisibilities(): array
    {
        return config('pawfit.features.friends') ? self::VISIBILITIES : ['public', 'unlisted', 'private'];
    }

    protected $fillable = [
        'owner_id', 'name', 'species', 'bio', 'tags', 'palette',
        'visibility', 'is_nsfw', 'is_representative', 'avatar_media_id', 'removed_at', 'allow_embed_api',
    ];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'palette' => 'array',
            'is_nsfw' => 'boolean',
            'is_representative' => 'boolean',
            'allow_embed_api' => 'boolean',
            'removed_at' => 'datetime',
        ];
    }

    public function isRemoved(): bool
    {
        return $this->removed_at !== null;
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function media(): HasMany
    {
        return $this->hasMany(Media::class)->orderBy('sort_order')->orderBy('created_at');
    }

    public function avatarMedia(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'avatar_media_id');
    }

    /** 目前有效（未撤銷）的分享連結，每隻獸設同時只有一條。 */
    public function shareLink(): HasOne
    {
        return $this->hasOne(ShareLink::class)->whereNull('revoked_at')->latest();
    }

    public function shareLinks(): HasMany
    {
        return $this->hasMany(ShareLink::class);
    }

    public function commissionKits(): HasMany
    {
        return $this->hasMany(CommissionKit::class);
    }

    public function isPublic(): bool
    {
        return $this->visibility === 'public';
    }

    public function isPrivate(): bool
    {
        return $this->visibility === 'private';
    }

    /** 嵌入與公開 API 是否開放（FR-7.1）：單隻覆寫優先，否則繼承用戶總開關。 */
    public function embedEnabled(): bool
    {
        return $this->allow_embed_api ?? (bool) $this->owner->allow_embed_api;
    }
}
