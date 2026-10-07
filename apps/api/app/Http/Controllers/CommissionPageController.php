<?php

namespace App\Http\Controllers;

use App\Http\Resources\CommissionKitResource;
use App\Http\Resources\MediaResource;
use App\Http\Resources\PublicUserResource;
use App\Models\CommissionKit;
use App\Models\Media;
use App\Services\MediaStorage;
use App\Services\Visibility;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * 委託需求單公開頁（FR-6.4）：/c/{slug} 的資料來源與合成圖出口。
 * 需求單固定 unlisted（知道連結即可看）；NSFW 需求單對訪客整份不顯示（FR-6.5）。
 */
class CommissionPageController extends Controller
{
    public function __construct(private readonly Visibility $visibility) {}

    /** GET /api/commission/{slug} */
    public function show(Request $request, string $slug): JsonResponse
    {
        $kit = CommissionKit::with('fursona.owner')->where('slug', $slug)->first();
        abort_if(! $kit, 404);

        $viewer = $request->user('sanctum');
        $state = $this->visibility->kitState($viewer, $kit);
        abort_if($state === null, 404);

        $request->attributes->set('kit_slug', $kit->slug);

        $media = $kit->media()->filter(function (Media $m) use ($viewer, $kit) {
            $s = $this->visibility->mediaStateViaKit($viewer, $m, $kit);
            if ($s === null) {
                return false;
            }
            $m->setAttribute('view_state', $s);

            return true;
        })->values();

        $og = $media->first(fn (Media $m) => ! $m->isNsfwContent());
        $fursona = $kit->fursona;

        return response()->json([
            'kit' => new CommissionKitResource($kit),
            'media' => MediaResource::collection($media),
            'owner' => new PublicUserResource($fursona->owner),
            'fursona' => [
                'id' => $fursona->id,
                'name' => $fursona->name,
                'share_url' => $kit->snapshot['share_url'] ?? null,
            ],
            'state' => $state,
            'is_owner' => $viewer?->id === $kit->owner_id,
            // OG 圖：優先合成圖（SFW 需求單才有意義），否則第一張 SFW 參考圖
            'og_image_url' => ! $kit->is_nsfw && $kit->sheet_key && $kit->status === 'active'
                ? '/api/commission/'.$kit->slug.'/sheet'
                : ($og ? MediaResource::imgUrl($og, 'display', null, $kit->slug) : null),
        ]);
    }

    /** GET /api/commission/{slug}/sheet：合成圖經 ACL 判斷後 302 簽名 URL。 */
    public function sheet(Request $request, string $slug, MediaStorage $storage): RedirectResponse
    {
        $kit = CommissionKit::with('fursona.owner')->where('slug', $slug)->first();
        abort_if(! $kit || ! $kit->sheet_key, 404);
        abort_if($this->visibility->kitState($request->user('sanctum'), $kit) === null, 404);

        $ttl = config('pawfit.media.signed_url_ttl') * 60;

        return redirect()->away($storage->signedGet($kit->sheet_key))->withHeaders([
            'Cache-Control' => $kit->is_nsfw ? 'private, no-store' : 'public, max-age='.max(60, $ttl - 120),
        ]);
    }
}
