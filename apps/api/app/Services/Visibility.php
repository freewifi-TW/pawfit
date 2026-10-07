<?php

namespace App\Services;

use App\Models\Block;
use App\Models\CommissionKit;
use App\Models\Fursona;
use App\Models\Media;
use App\Models\ShareLink;
use App\Models\User;
use ArrayObject;
use Illuminate\Support\Collection;

/**
 * 全站唯一的可見性判斷點（SASD §0.1 原則 3）。
 *
 * 回傳值語意：
 *   null   → 不可見（對外一律當作不存在，回 404）
 *   'show' → 直接顯示
 *   'blur' → 以模糊呈現，點擊解鎖（viewer 偏好 = blur）
 *
 * 隱私 × NSFW 矩陣（FR-5.4）：
 *   內容 SFW  → 只看隱私
 *   內容 NSFW → 訪客／未完成 18+ 聲明／偏好 hide → null；blur → 'blur'；show → 'show'
 * 內容擁有者與管理員永遠 'show'。
 */
class Visibility
{
    /**
     * viewer × owner 的關係快取，掛在目前的 Request 上：
     * 同一請求內重複查同一擁有者不再打資料庫；且不會跨請求殘留
     * （Laravel 會把 controller 實例快取在 Route 上，Octane／測試中多個請求共用同一個 service 實例）。
     */
    private function relationMemo(): ArrayObject
    {
        $attrs = app('request')->attributes;
        if (! $attrs->has('_visibility_relations')) {
            $attrs->set('_visibility_relations', new ArrayObject);
        }

        return $attrs->get('_visibility_relations');
    }

    /**
     * 瀏覽者與內容擁有者的關係（Phase 2 §2.2）：
     *   blocked → 任一方封鎖另一方（互不可見）；friend → 已接受的好友。
     * 訪客或本人一律 blocked=false、friend=false。
     *
     * @return array{blocked: bool, friend: bool}
     */
    public function relation(?User $viewer, string $ownerId): array
    {
        if ($viewer === null || $viewer->id === $ownerId) {
            return ['blocked' => false, 'friend' => false];
        }
        $memo = $this->relationMemo();
        $key = $viewer->id.'|'.$ownerId;
        if (! isset($memo[$key])) {
            $memo[$key] = [
                'blocked' => Block::eitherWay($viewer->id, $ownerId),
                'friend' => $viewer->isFriendsWith($ownerId),
            ];
        }

        return $memo[$key];
    }

    /** 封鎖判斷只存在這裡（Phase 2 R-4）：任一方封鎖 → 對方的主頁、獸設、圖片一律視為不存在。 */
    public function isBlockedBetween(?User $viewer, string $ownerId): bool
    {
        return $this->relation($viewer, $ownerId)['blocked'];
    }

    public function isOwner(?User $viewer, Fursona|Media $subject): bool
    {
        return $viewer !== null && $viewer->id === $subject->owner_id;
    }

    public function isPrivileged(?User $viewer, Fursona|Media $subject): bool
    {
        return $this->isOwner($viewer, $subject) || ($viewer?->isAdmin() ?? false);
    }

    /** 分享連結是否對這隻獸設有效。 */
    public function linkGrants(?ShareLink $link, Fursona $fursona): bool
    {
        return $link !== null && $link->isActive() && $link->fursona_id === $fursona->id;
    }

    /** viewer 對 NSFW 內容的呈現方式。 */
    public function nsfwState(?User $viewer): ?string
    {
        if ($viewer === null || ! $viewer->hasConfirmedAdult()) {
            return null;
        }

        return match ($viewer->nsfw_pref) {
            'blur' => 'blur',
            'show' => 'show',
            default => null,
        };
    }

    public function fursonaState(?User $viewer, Fursona $fursona, ?ShareLink $link = null): ?string
    {
        if ($this->isPrivileged($viewer, $fursona)) {
            return 'show';
        }
        if ($fursona->isRemoved() || $fursona->owner->is_banned) {
            return null;
        }
        if ($this->isBlockedBetween($viewer, $fursona->owner_id)) {
            return null;
        }
        if (! $this->passesVisibility($fursona->visibility, $fursona, $link, $viewer)) {
            return null;
        }

        return $fursona->is_nsfw ? $this->nsfwState($viewer) : 'show';
    }

    public function mediaState(?User $viewer, Media $media, ?ShareLink $link = null): ?string
    {
        if ($this->isPrivileged($viewer, $media)) {
            return 'show';
        }
        $fursona = $media->fursona;
        $fursonaState = $this->fursonaState($viewer, $fursona, $link);
        if ($fursonaState === null || ! $media->isActive()) {
            return null;
        }
        if (! $this->passesVisibility($media->effectiveVisibility(), $fursona, $link, $viewer)) {
            return null;
        }
        if ($media->isNsfwContent()) {
            return $this->nsfwState($viewer);
        }

        return 'show';
    }

    /**
     * 過濾一組 media，回傳可見清單（附 state）與被隱藏的 NSFW 張數（分享頁提示用）。
     *
     * @return array{visible: Collection<int, Media>, hidden_nsfw: int}
     */
    public function filterMedia(?User $viewer, Collection $media, ?ShareLink $link = null): array
    {
        $hiddenNsfw = 0;
        $visible = $media->filter(function (Media $m) use ($viewer, $link, &$hiddenNsfw) {
            $state = $this->mediaState($viewer, $m, $link);
            if ($state === null) {
                // 只有「因 NSFW 而被隱藏」才計數：隱私不符的不提示，避免洩漏存在
                if ($m->isActive() && $m->isNsfwContent()
                    && $this->passesVisibility($m->effectiveVisibility(), $m->fursona, $link, $viewer)) {
                    $hiddenNsfw++;
                }

                return false;
            }
            $m->setAttribute('view_state', $state);

            return true;
        })->values();

        return ['visible' => $visible, 'hidden_nsfw' => $hiddenNsfw];
    }

    /**
     * 嵌入與公開 API（FR-7）：觀看者永遠視同訪客（FR-5.4 矩陣第一欄），
     * 且用戶總開關與獸設覆寫都要允許。NSFW 獸設因訪客規則自然不可嵌入。
     */
    public function embeddable(Fursona $fursona, ?ShareLink $link = null): bool
    {
        if (! $fursona->embedEnabled()) {
            return false;
        }

        return $this->fursonaState(null, $fursona, $link) === 'show';
    }

    /**
     * 委託需求單（FR-6.4／6.5）：固定 unlisted——知道 slug 即可看；
     * 整份 NSFW 時對訪客／未聲明／偏好 hide 不顯示；擁有者與管理員永遠可見。
     */
    public function kitState(?User $viewer, CommissionKit $kit): ?string
    {
        if ($viewer !== null && ($viewer->id === $kit->owner_id || $viewer->isAdmin())) {
            return 'show';
        }
        $fursona = $kit->fursona;
        if (! $kit->isActive() || $fursona === null || $fursona->isRemoved() || $fursona->owner->is_banned) {
            return null;
        }

        return $kit->is_nsfw ? $this->nsfwState($viewer) : 'show';
    }

    /**
     * 經需求單觀看參考圖：需求單是擁有者挑選的快照，圖片本身的隱私設定不再另判，
     * 但下架（removed）與 NSFW 矩陣仍然生效；不在需求單裡的圖一律不可見。
     */
    public function mediaStateViaKit(?User $viewer, Media $media, CommissionKit $kit): ?string
    {
        if ($this->isPrivileged($viewer, $media)) {
            return 'show';
        }
        if ($this->kitState($viewer, $kit) === null) {
            return null;
        }
        if (! $media->isActive() || ! in_array($media->id, $kit->media_ids ?? [], true)) {
            return null;
        }

        return $media->isNsfwContent() ? $this->nsfwState($viewer) : 'show';
    }

    private function passesVisibility(string $visibility, Fursona $fursona, ?ShareLink $link, ?User $viewer = null): bool
    {
        return match ($visibility) {
            'public' => true,
            'unlisted' => $this->linkGrants($link, $fursona),
            // 限好友（Phase 2 FR-B2）：需登入且為已接受的好友；分享連結不放行，訪客視同 private
            'friends' => $this->relation($viewer, $fursona->owner_id)['friend'],
            default => false, // private（以及未知值一律拒絕）
        };
    }
}
