<?php

namespace App\Http\Controllers;

use App\Http\Resources\FursonaResource;
use App\Http\Resources\MediaResource;
use App\Http\Resources\PublicUserResource;
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

    /** GET /api/users/{pawfitId} */
    public function profile(Request $request, string $pawfitId): JsonResponse
    {
        $user = User::whereRaw('lower(pawfit_id) = ?', [strtolower($pawfitId)])->first();
        abort_if(! $user || $user->is_banned, 404);

        $viewer = $request->user('sanctum');
        $isOwner = $viewer?->id === $user->id;

        $fursonas = $user->fursonas()
            ->with(['avatarMedia', 'owner', 'media' => fn ($q) => $q->where('status', 'active')])
            ->withCount(['media' => fn ($q) => $q->where('status', 'active')])
            ->where('visibility', 'public')
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
        ]);
    }
}
