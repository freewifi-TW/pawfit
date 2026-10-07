<?php

namespace App\Http\Controllers;

use App\Models\CommissionKit;
use App\Models\Media;
use App\Models\Post;
use App\Models\ShareLink;
use App\Services\MediaStorage;
use App\Services\Visibility;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * GET /api/img/{media}?v=thumb|display|original&s={slug}&k={kitSlug}
 * 唯一的圖片出口：經 Visibility 判斷後 302 到短效簽名 URL（SASD §2.1 讀取流程）。
 * s＝分享連結、k＝委託需求單（FR-6.4）、p＝貼文（Phase 2）；需求單／貼文情境的浮水印沿用該獸設目前分享連結的設定。
 */
class ImageController extends Controller
{
    public function show(Request $request, Media $media, Visibility $visibility, MediaStorage $storage): RedirectResponse
    {
        $viewer = $request->user('sanctum');
        $variant = (string) $request->query('v', 'thumb');
        $link = $request->filled('s') ? ShareLink::where('slug', $request->query('s'))->first() : null;
        $kit = $request->filled('k') ? CommissionKit::with('fursona.owner')->where('slug', $request->query('k'))->first() : null;
        $post = $request->filled('p') && config('pawfit.features.feed') ? Post::with('author')->find($request->query('p')) : null;

        $media->loadMissing('fursona.owner');
        $state = match (true) {
            $kit !== null => $visibility->mediaStateViaKit($viewer, $media, $kit),
            $post !== null => $visibility->mediaStateViaPost($viewer, $media, $post),
            default => $visibility->mediaState($viewer, $media, $link),
        };
        abort_if($state === null, 404);
        if (($kit || $post) && ! $link) {
            $link = $media->fursona->shareLink;
        }

        $privileged = $visibility->isPrivileged($viewer, $media);

        $key = match ($variant) {
            'original' => $privileged ? $media->storage_key : null,
            'display' => $this->displayKey($media, $link, $privileged),
            default => $media->thumb_key ?? $media->display_key ?? ($privileged ? $media->storage_key : null),
        };
        abort_if($key === null, $variant === 'original' ? 403 : 404);

        $ttl = config('pawfit.media.signed_url_ttl') * 60;
        $cacheable = ! $privileged && $media->effectiveVisibility() === 'public' && ! $media->isNsfwContent();

        return redirect()->away($storage->signedGet($key))->withHeaders([
            // 公開 SFW 內容允許短暫快取，減少後端請求；其餘一律不快取
            'Cache-Control' => $cacheable ? 'public, max-age='.max(60, $ttl - 120) : 'private, no-store',
        ]);
    }

    /** 分享連結開啟浮水印時，非擁有者拿到的是浮水印版。 */
    private function displayKey(Media $media, ?ShareLink $link, bool $privileged): ?string
    {
        if (! $privileged && $link?->watermark && $media->watermarked_key) {
            return $media->watermarked_key;
        }

        return $media->display_key ?? ($privileged ? $media->storage_key : null);
    }
}
