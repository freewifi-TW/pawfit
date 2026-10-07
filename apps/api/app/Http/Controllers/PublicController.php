<?php

namespace App\Http\Controllers;

use App\Http\Resources\FursonaResource;
use App\Http\Resources\MediaResource;
use App\Http\Resources\PublicUserResource;
use App\Models\Friendship;
use App\Models\Fursona;
use App\Models\Media;
use App\Models\ShareLink;
use App\Models\User;
use App\Services\Visibility;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** 訪客可讀的端點：分享頁與個人主頁。所有可見性一律經 Visibility。 */
class PublicController extends Controller
{
    public function __construct(private readonly Visibility $visibility) {}

    /** GET /api/share/{slug} */
    public function share(Request $request, string $slug): JsonResponse
    {
        $link = ShareLink::with('fursona.owner')->where('slug', $slug)->first();
        abort_if(! $link || ! $link->isActive(), 404);

        $viewer = $request->user('sanctum');
        $fursona = $link->fursona;
        $state = $this->visibility->fursonaState($viewer, $fursona, $link);
        abort_if($state === null, 404);

        $request->attributes->set('share_slug', $link->slug);

        $all = $fursona->media()->with('fursona.owner')->where('status', '!=', 'removed')->get();
        $filtered = $this->visibility->filterMedia($viewer, $all, $link);
        $fursona->setRelation('media', $filtered['visible']);

        $og = $filtered['visible']->first(fn (Media $m) => ! $m->isNsfwContent());

        return response()->json([
            'fursona' => new FursonaResource($fursona),
            'owner' => new PublicUserResource($fursona->owner),
            'state' => $state,
            'hidden_nsfw_count' => $filtered['hidden_nsfw'],
            'is_owner' => $this->visibility->isOwner($viewer, $fursona),
            'share' => ['slug' => $link->slug, 'url' => $link->url(), 'watermark' => $link->watermark],
            // oEmbed discovery link 只在可嵌入時輸出（FR-7.2）
            'embed_enabled' => $this->visibility->embeddable($fursona, $link),
            // OG 圖一律 SFW，且走簽名 URL 302
            'og_image_url' => $og ? MediaResource::imgUrl($og, 'display', $link->slug) : null,
        ]);
    }

    /** @return array{status: string, friendship_id: string|null, blocked_by_me: bool}|null */
    private function relationPayload(?User $viewer, User $target): ?array
    {
        if (! config('pawfit.features.friends') || $viewer === null || $viewer->id === $target->id) {
            return null;
        }
        $f = Friendship::between($viewer->id, $target->id);
        $status = match (true) {
            $f === null => 'none',
            $f->isAccepted() => 'friends',
            $f->requested_by === $viewer->id => 'pending_out',
            default => 'pending_in',
        };

        return ['status' => $status, 'friendship_id' => $f?->id, 'blocked_by_me' => false];
    }

    /** GET /api/users/{pawfitId} */
    public function profile(Request $request, string $pawfitId): JsonResponse
    {
        $user = User::whereRaw('lower(pawfit_id) = ?', [strtolower($pawfitId)])->first();
        abort_if(! $user || $user->is_banned, 404);

        $viewer = $request->user('sanctum');
        $isOwner = $viewer?->id === $user->id;
        // 封鎖：任一方封鎖另一方 → 主頁視為不存在（Phase 2 §2.2）
        abort_if($this->visibility->isBlockedBetween($viewer, $user->id), 404);

        $fursonas = $user->fursonas()
            ->with(['avatarMedia', 'owner', 'media' => fn ($q) => $q->where('status', 'active')])
            ->withCount(['media' => fn ($q) => $q->where('status', 'active')])
            // 個人主頁列出公開獸設；好友另可看到限好友的（FR-B2）
            ->whereIn('visibility', $this->visibility->relation($viewer, $user->id)['friend'] ? ['public', 'friends'] : ['public'])
            ->orderByDesc('is_representative')->orderBy('created_at')
            ->get()
            ->filter(fn (Fursona $f) => $this->visibility->fursonaState($viewer, $f) !== null)
            ->map(function (Fursona $f) use ($viewer) {
                $f->setRelation('media', $this->visibility->filterMedia($viewer, $f->media)['visible']);

                return $f;
            })
            ->values();

        $representative = $fursonas->first(fn (Fursona $f) => $f->is_representative);

        return response()->json([
            'user' => new PublicUserResource($user),
            'is_owner' => $isOwner,
            'embed_enabled' => (bool) $user->allow_embed_api,
            'representative' => $representative ? new FursonaResource($representative) : null,
            'fursonas' => FursonaResource::collection($fursonas),
            'stats' => [
                'public_count' => $fursonas->count(),
                'total_count' => $isOwner ? $user->fursonas()->count() : null,
            ],
            // Phase 2 M1：瀏覽者與這位用戶的關係（加好友／封鎖按鈕用）
            'relation' => $this->relationPayload($viewer, $user),
        ]);
    }
}
