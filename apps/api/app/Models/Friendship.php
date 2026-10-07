<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** 好友關係（FR-B1）：一組關係一列，user_a < user_b；status pending → accepted。 */
class Friendship extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    protected $fillable = ['user_a', 'user_b', 'status', 'requested_by', 'accepted_at'];

    protected function casts(): array
    {
        return ['accepted_at' => 'datetime', 'created_at' => 'datetime'];
    }

    /** @return array{0: string, 1: string} */
    public static function pair(string $x, string $y): array
    {
        return strcmp($x, $y) < 0 ? [$x, $y] : [$y, $x];
    }

    public static function between(string $x, string $y): ?self
    {
        [$a, $b] = static::pair($x, $y);

        return static::where('user_a', $a)->where('user_b', $b)->first();
    }

    public function scopeInvolving(Builder $q, string $userId): Builder
    {
        return $q->where(fn (Builder $w) => $w->where('user_a', $userId)->orWhere('user_b', $userId));
    }

    public function scopeAccepted(Builder $q): Builder
    {
        return $q->where('status', 'accepted');
    }

    public function isAccepted(): bool
    {
        return $this->status === 'accepted';
    }

    public function otherId(string $userId): string
    {
        return $this->user_a === $userId ? $this->user_b : $this->user_a;
    }

    public function userA(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_a');
    }

    public function userB(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_b');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
