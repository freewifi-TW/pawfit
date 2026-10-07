<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** 封鎖（FR-B1.2）：單向記錄，但可見性判斷時任一方封鎖即互不可見。 */
class Block extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    protected $fillable = ['blocker_id', 'blocked_id'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    /** 任一方向是否存在封鎖。 */
    public static function eitherWay(string $x, string $y): bool
    {
        return static::where(fn ($q) => $q->where('blocker_id', $x)->where('blocked_id', $y))
            ->orWhere(fn ($q) => $q->where('blocker_id', $y)->where('blocked_id', $x))
            ->exists();
    }

    public function blocked(): BelongsTo
    {
        return $this->belongsTo(User::class, 'blocked_id');
    }
}
