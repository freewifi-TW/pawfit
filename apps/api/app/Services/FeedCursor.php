<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/** 河道 (created_at, id) cursor 分頁（SASD Phase 2 §2.3）：base64url("<iso8601>|<uuid>")。 */
class FeedCursor
{
    public const PAGE = 20;

    public function encode(Carbon $createdAt, string $id): string
    {
        return rtrim(strtr(base64_encode($createdAt->toIso8601ZuluString('microsecond').'|'.$id), '+/', '-_'), '=');
    }

    /** @return array{0: Carbon, 1: string}|null */
    public function decode(?string $cursor): ?array
    {
        if (! $cursor) {
            return null;
        }
        $raw = base64_decode(strtr($cursor, '-_', '+/'), true);
        if ($raw === false || ! str_contains($raw, '|')) {
            abort(422, 'Invalid cursor.');
        }
        [$ts, $id] = explode('|', $raw, 2);
        try {
            $at = Carbon::parse($ts);
        } catch (\Throwable) {
            abort(422, 'Invalid cursor.');
        }
        if (! preg_match('/^[0-9a-f-]{36}$/', $id)) {
            abort(422, 'Invalid cursor.');
        }

        return [$at, $id];
    }

    /** 套用 cursor 條件與排序，多取一筆判斷有沒有下一頁。 */
    public function apply(Builder $q, ?string $cursor, string $table = 'posts'): Builder
    {
        if ($c = $this->decode($cursor)) {
            [$at, $id] = $c;
            $q->where(function (Builder $w) use ($at, $id, $table) {
                $w->where("{$table}.created_at", '<', $at)
                    ->orWhere(fn (Builder $x) => $x->where("{$table}.created_at", $at)->where("{$table}.id", '<', $id));
            });
        }

        return $q->orderByDesc("{$table}.created_at")->orderByDesc("{$table}.id")->limit(self::PAGE + 1);
    }
}
