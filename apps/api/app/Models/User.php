<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

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
