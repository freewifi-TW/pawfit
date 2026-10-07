<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

/**
 * 委託需求單（FR-6）：資料快照 + 分享連結。
 * snapshot 結構：
 *   fursona: {name, species, bio, tags[], palette[{hex,name,note}]}
 *   owner:   {pawfit_id, display_name}
 *   media:   [{id, kind, caption, credit_name, credit_url, is_nsfw, width, height}]（依勾選順序）
 *   request: {composition, scene, size, usage, budget, deadline, notes}
 *   share_url: 建立當下獸設的分享頁網址（可空）
 */
class CommissionKit extends Model
{
    use HasFactory, HasUuids;

    public const KINDS = ['art2d', 'fursuit'];

    public const STATUSES = ['processing', 'active', 'failed', 'revoked'];

    public const REQUEST_FIELDS = ['composition', 'scene', 'size', 'usage', 'budget', 'deadline', 'notes'];

    protected $fillable = [
        'fursona_id', 'owner_id', 'kind', 'slug', 'snapshot', 'media_ids', 'brief_text', 'brief_source',
        'sheet_key', 'is_nsfw', 'size_card_id', 'pin_set', 'status', 'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'snapshot' => 'array',
            'media_ids' => 'array',
            'brief_text' => 'array',
            'pin_set' => 'array',
            'is_nsfw' => 'boolean',
            'revoked_at' => 'datetime',
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

    public function isActive(): bool
    {
        return $this->revoked_at === null && $this->status !== 'revoked';
    }

    /** 需求單內的參考圖（只取仍存在且未下架的），依 media_ids 順序。 */
    public function media(): Collection
    {
        $ids = array_values($this->media_ids ?? []);
        if ($ids === []) {
            return collect();
        }
        $order = array_flip($ids);

        return Media::with('fursona.owner')->whereIn('id', $ids)->where('status', '!=', 'removed')->get()
            ->sortBy(fn (Media $m) => $order[$m->id] ?? PHP_INT_MAX)->values();
    }

    public function url(): string
    {
        return config('pawfit.frontend_url').'/c/'.$this->slug;
    }

    /** 與 ShareLink 相同的 nanoid 風格 slug（10 字元英數）。 */
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
}
