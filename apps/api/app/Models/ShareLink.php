<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShareLink extends Model
{
    use HasUuids;

    protected $fillable = ['fursona_id', 'slug', 'watermark', 'revoked_at'];

    protected function casts(): array
    {
        return [
            'watermark' => 'boolean',
            'revoked_at' => 'datetime',
        ];
    }

    public function fursona(): BelongsTo
    {
        return $this->belongsTo(Fursona::class);
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null;
    }

    /** nanoid 風格：10 字元、URL 安全、大小寫英數。 */
    public static function generateSlug(): string
    {
        $alphabet = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        do {
            $slug = '';
            for ($i = 0; $i < 10; $i++) {
                $slug .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
        } while (static::where('slug', $slug)->exists());

        return $slug;
    }

    public function url(): string
    {
        return config('pawfit.frontend_url').'/s/'.$this->slug;
    }
}
