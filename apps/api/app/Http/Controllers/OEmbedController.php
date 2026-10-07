<?php

namespace App\Http\Controllers;

use App\Http\Resources\MediaResource;
use App\Models\Fursona;
use App\Models\ShareLink;
use App\Models\User;
use App\Services\Visibility;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * oEmbed（FR-7.2）：GET /api/oembed?url=&format=json&maxwidth=&maxheight=
 * 支援 /s/{slug}（獸設分享頁）與 /u/{pawfitId}（個人主頁 → 代表獸設的卡片）。
 * 回 type=rich，html 為 /embed/{slug} 的 iframe；只在可嵌入時回應，否則 404。
 */
class OEmbedController extends Controller
{
    public function __construct(private readonly Visibility $visibility) {}

    public function show(Request $request): JsonResponse
    {
        $data = $request->validate([
            'url' => ['required', 'url', 'max:500'],
            'format' => ['sometimes', 'in:json'],
            'maxwidth' => ['sometimes', 'integer', 'min:200', 'max:2000'],
            'maxheight' => ['sometimes', 'integer', 'min:120', 'max:2000'],
        ]);

        $target = $this->resolveTarget($data['url']);
        abort_if($target === null, 404);
        [$link, $fursona] = $target;

        $sizes = config('pawfit.embed.sizes');
        [$w, $h] = $sizes['md'];
        if (isset($data['maxwidth']) && $data['maxwidth'] < $w) {
            [$w, $h] = $data['maxwidth'] < $sizes['md'][0] ? $sizes['sm'] : $sizes['md'];
        }
        if (isset($data['maxheight']) && $data['maxheight'] < $h) {
            [$w, $h] = $sizes['sm'];
        }

        $front = config('pawfit.frontend_url');
        $cardUrl = $front.'/embed/'.$link->slug;
        $media = $fursona->media()->with('fursona.owner')->where('status', 'active')->get();
        $cover = $this->visibility->filterMedia(null, $media, $link)['visible']->first();
        $title = $fursona->species ? "{$fursona->name} · {$fursona->species}" : $fursona->name;

        $html = sprintf(
            '<iframe src="%s" width="%d" height="%d" style="border:0;border-radius:20px;max-width:100%%" loading="lazy" title="%s" allow="clipboard-write" referrerpolicy="strict-origin"></iframe>',
            e($cardUrl), $w, $h, e($title.' · Pawfit'),
        );

        return response()->json(array_filter([
            'version' => '1.0',
            'type' => 'rich',
            'provider_name' => 'Pawfit',
            'provider_url' => $front,
            'title' => $title,
            'author_name' => $fursona->owner->display_name ?? $fursona->owner->name,
            'author_url' => $front.'/u/'.$fursona->owner->pawfit_id,
            'html' => $html,
            'width' => $w,
            'height' => $h,
            'thumbnail_url' => $cover ? $front.MediaResource::imgUrl($cover, 'display', $link->slug) : null,
            'thumbnail_width' => $cover?->width,
            'thumbnail_height' => $cover?->height,
            'cache_age' => (int) config('pawfit.embed.json_max_age'),
        ], fn ($v) => $v !== null));
    }

    /** @return array{0: ShareLink, 1: Fursona}|null */
    private function resolveTarget(string $url): ?array
    {
        $parts = parse_url($url);
        $frontHost = parse_url(config('pawfit.frontend_url'), PHP_URL_HOST);
        if (! $parts || ! isset($parts['host']) || strcasecmp($parts['host'], (string) $frontHost) !== 0) {
            return null;
        }
        $path = trim($parts['path'] ?? '', '/');

        if (preg_match('#^s/([A-Za-z0-9]{6,32})$#', $path, $m)) {
            $link = ShareLink::with('fursona.owner')->where('slug', $m[1])->first();

            return $link && $link->isActive() && $this->visibility->embeddable($link->fursona, $link) ? [$link, $link->fursona] : null;
        }

        if (preg_match('#^u/([A-Za-z0-9_]{3,20})$#', $path, $m)) {
            $user = User::whereRaw('lower(pawfit_id) = ?', [strtolower($m[1])])->first();
            if (! $user || $user->is_banned || ! $user->allow_embed_api) {
                return null;
            }
            $rep = $user->fursonas()->with(['owner', 'shareLink'])->where('is_representative', true)->first();
            $link = $rep?->shareLink;

            return $rep && $link && $this->visibility->embeddable($rep, $link) ? [$link, $rep] : null;
        }

        return null;
    }
}
