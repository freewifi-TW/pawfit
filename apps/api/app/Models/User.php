<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasUuids, Notifiable;

    public const NSFW_PREFS = ['hide', 'blur', 'show'];

    protected $fillable = [
        'google_id', 'email', 'name', 'google_avatar_url',
        'pawfit_id', 'display_name', 'avatar_media_id', 'nsfw_pref',
        'adult_confirmed_at', 'tos_accepted_at', 'is_banned', 'last_login_at', 'locale', 'allow_embed_api',
    ];

    protected $hidden = ['remember_token', 'google_id'];

    protected function casts(): array
    {
        return [
            'adult_confirmed_at' => 'datetime',
            'tos_accepted_at' => 'datetime',
            'last_login_at' => 'datetime',
            'is_banned' => 'boolean',
            'allow_embed_api' => 'boolean',
        ];
    }

    public function fursonas(): HasMany
    {
        return $this->hasMany(Fursona::class, 'owner_id');
    }

    public function media(): HasMany
    {
        return $this->hasMany(Media::class, 'owner_id');
    }

    public function avatarMedia(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'avatar_media_id');
    }

    /** 與某用戶是否為已接受的好友。 */
    public function isFriendsWith(string $userId): bool
    {
        return Friendship::between($this->id, $userId)?->isAccepted() ?? false;
    }

    /** 好友 id 清單（河道查詢用）。 */
    public function friendIds(): array
    {
        return Friendship::involving($this->id)->accepted()->get()
            ->map(fn (Friendship $f) => $f->otherId($this->id))->all();
    }

    /** 這批貼文中我按過讚的 id。 */
    public function likedPostIds(array $postIds): array
    {
        return DB::table('post_likes')->where('user_id', $this->id)->whereIn('post_id', $postIds)->pluck('post_id')->all();
    }

    /** 我封鎖的 + 封鎖我的（查詢層排除用）。 */
    public function blockedEitherWayIds(): array
    {
        return Block::where('blocker_id', $this->id)->pluck('blocked_id')
            ->merge(Block::where('blocked_id', $this->id)->pluck('blocker_id'))
            ->unique()->values()->all();
    }

    public function isAdmin(): bool
    {
        return in_array(strtolower((string) $this->email), config('pawfit.admin_emails', []), true);
    }

    public function isOnboarded(): bool
    {
        return $this->pawfit_id !== null && $this->tos_accepted_at !== null;
    }

    public function hasConfirmedAdult(): bool
    {
        return $this->adult_confirmed_at !== null;
    }

    /** 有效的 NSFW 偏好：未完成 18+ 聲明一律視為 hide。 */
    public function effectiveNsfwPref(): string
    {
        return $this->hasConfirmedAdult() ? $this->nsfw_pref : 'hide';
    }

    public function storageUsedBytes(): int
    {
        return (int) $this->media()->where('status', '!=', 'removed')->sum('bytes');
    }

    public function uploadsToday(): int
    {
        return $this->media()->where('created_at', '>=', now()->startOfDay())->count();
    }
}
