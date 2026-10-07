<?php

namespace App\Services;

use App\Models\CommissionKit;
use App\Models\Fursona;
use App\Models\Media;
use App\Models\ShareLink;
use App\Models\User;
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
        if (! $this->passesVisibility($fursona->visibility, $fursona, $link)) {
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
        if (! $this->passesVisibility($media->effectiveVisibility(), $fursona, $link)) {
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
                    && $this->passesVisibility($m->effectiveVisibility(), $m->fursona, $link)) {
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

    private function passesVisibility(string $visibility, Fursona $fursona, ?ShareLink $link): bool
    {
        return match ($visibility) {
            'public' => true,
            'unlisted' => $this->linkGrants($link, $fursona),
            default => false, // private（以及未來未知值一律拒絕）
        };
    }
}
