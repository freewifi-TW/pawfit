<?php

namespace App\Http\Controllers;

use App\Http\Resources\MediaResource;
use App\Models\Fursona;
use App\Models\Media;
use App\Models\ShareLink;
use App\Models\User;
use App\Services\PaletteSvg;
use App\Services\Visibility;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * 公開 JSON API v1 與 SVG 色票卡（FR-7.3、FR-7.5）。
 * - 匿名、GET only；觀看者永遠視同訪客，可見性一律經 Visibility::embeddable()
 * - 獸設以「分享連結 slug」為鍵（與分享頁語意一致：unlisted 知道 slug 即可讀，private 一律 404）
 * - 用戶或獸設的 allow_embed_api 關閉 → 404（不洩漏存在）
 * - 圖片只給 /api/img 路徑，不給 R2 直鏈
 */
class PublicApiController extends Controller
{
    public function __construct(private readonly Visibility $visibility) {}

    /** GET /api/v1/public/users/{pawfitId} */
    public function user(string $pawfitId): JsonResponse
    {
        $user = User::whereRaw('lower(pawfit_id) = ?', [strtolower($pawfitId)])->first();
        abort_if(! $user || $user->is_banned || ! $user->allow_embed_api, 404);

        $fursonas = $user->fursonas()
            ->with(['owner', 'avatarMedia', 'shareLink'])
            ->where('visibility', 'public')
            ->orderByDesc('is_representative')->orderBy('created_at')
            ->get()
            ->filter(fn (Fursona $f) => $this->visibility->embeddable($f))
            ->values();

        $representative = $fursonas->first(fn (Fursona $f) => $f->is_representative);

        return response()->json([
            'pawfit_id' => $user->pawfit_id,
            'display_name' => $user->display_name ?? $user->name,
            'avatar_url' => $this->abs(MediaResource::avatarUrl($user)),
            'profile_url' => $this->front('/u/'.$user->pawfit_id),
            'representative_slug' => $representative?->shareLink?->slug,
            'fursonas' => $fursonas->map(fn (Fursona $f) => [
                'name' => $f->name,
                'species' => $f->species,
                'tags' => $f->tags ?? [],
                'avatar_url' => $this->abs(MediaResource::avatarUrl($f)),
                'is_representative' => $f->is_representative,
                // 沒有分享連結的公開獸設無法以 slug 讀取；擁有者需先建立分享連結
                'slug' => $f->shareLink?->slug,
                'url' => $f->shareLink ? $f->shareLink->url() : null,
            ])->all(),
        ]);
    }

    /** GET /api/v1/public/fursonas/{slug} */
    public function fursona(string $slug): JsonResponse
    {
        [$link, $fursona] = $this->resolve($slug);
        $media = $this->visibleMedia($fursona, $link);

        $credits = $media
            ->filter(fn (Media $m) => filled($m->credit_name))
            ->unique(fn (Media $m) => strtolower(trim($m->credit_name)).'|'.($m->credit_url ?? ''))
            ->map(fn (Media $m) => ['name' => $m->credit_name, 'url' => $m->credit_url])
            ->values();

        $cover = $media->first();

        return response()->json([
            'slug' => $link->slug,
            'name' => $fursona->name,
            'species' => $fursona->species,
            'bio' => $fursona->bio,
            'tags' => $fursona->tags ?? [],
            'palette' => collect($fursona->palette ?? [])->map(fn (array $p) => [
                'hex' => $p['hex'] ?? null,
                'name' => $p['name'] ?? '',
                'note' => $p['note'] ?? '',
            ])->all(),
            'is_nsfw' => $fursona->is_nsfw,
            'avatar_url' => $this->abs(MediaResource::avatarUrl($fursona)),
            'cover_url' => $cover ? $this->abs(MediaResource::imgUrl($cover, 'display', $link->slug)) : null,
            'media_count' => $media->count(),
            'credits' => $credits->all(),
            'owner' => [
                'pawfit_id' => $fursona->owner->pawfit_id,
                'display_name' => $fursona->owner->display_name ?? $fursona->owner->name,
                'profile_url' => $this->front('/u/'.$fursona->owner->pawfit_id),
            ],
            'share_url' => $link->url(),
            'embed' => [
                'card_url' => $this->front('/embed/'.$link->slug),
                'palette_svg_url' => $this->front('/embed/'.$link->slug.'/palette.svg'),
                'media_url' => $this->front('/api/v1/public/fursonas/'.$link->slug.'/media'),
            ],
            'updated_at' => $fursona->updated_at,
        ]);
    }

    /** GET /api/v1/public/fursonas/{slug}/media?cursor= */
    public function media(Request $request, string $slug): JsonResponse
    {
        [$link, $fursona] = $this->resolve($slug);
        $all = $this->visibleMedia($fursona, $link);

        $size = (int) config('pawfit.embed.media_page_size');
        $offset = $this->decodeCursor((string) $request->query('cursor', ''));
        $page = $all->slice($offset, $size)->values();
        $next = $offset + $size < $all->count() ? $this->encodeCursor($offset + $size) : null;

        return response()->json([
            'data' => $page->map(fn (Media $m) => [
                'id' => $m->id,
                'kind' => $m->kind,
                'caption' => $m->caption,
                'credit_name' => $m->credit_name,
                'credit_url' => $m->credit_url,
                'width' => $m->width,
                'height' => $m->height,
                'url' => $this->abs(MediaResource::imgUrl($m, 'display', $link->slug)),
                'thumb_url' => $this->abs(MediaResource::imgUrl($m, 'thumb', $link->slug)),
                'created_at' => $m->created_at,
            ])->all(),
            'next_cursor' => $next,
            'total' => $all->count(),
        ]);
    }

    /** GET /api/v1/public/fursonas/{slug}/palette.svg?theme=light|dark&layout=row|grid */
    public function paletteSvg(Request $request, string $slug, PaletteSvg $svg): Response
    {
        [$link, $fursona] = $this->resolve($slug);

        $theme = in_array($request->query('theme'), PaletteSvg::THEMES, true) ? $request->query('theme') : 'light';
        $layout = in_array($request->query('layout'), PaletteSvg::LAYOUTS, true) ? $request->query('layout') : 'row';
        $ttl = (int) config('pawfit.embed.svg_max_age');

        $key = "palette-svg:{$link->slug}:{$theme}:{$layout}:".$fursona->updated_at?->timestamp;
        $body = Cache::remember($key, $ttl, fn () => $svg->render($fursona, $theme, $layout));

        return response($body, 200, [
            'Content-Type' => 'image/svg+xml; charset=utf-8',
            'Cache-Control' => "public, max-age={$ttl}",
            'Content-Disposition' => 'inline; filename="'.$link->slug.'-palette.svg"',
        ]);
    }

    /** @return array{0: ShareLink, 1: Fursona} */
    private function resolve(string $slug): array
    {
        $link = ShareLink::with('fursona.owner')->where('slug', $slug)->first();
        abort_if(! $link || ! $link->isActive(), 404);
        $fursona = $link->fursona;
        abort_unless($this->visibility->embeddable($fursona, $link), 404);

        return [$link, $fursona];
    }

    /** 訪客可見的 media（排序沿用圖庫）。 */
    private function visibleMedia(Fursona $fursona, ShareLink $link): Collection
    {
        $all = $fursona->media()->with('fursona.owner')->where('status', 'active')->get();

        return $this->visibility->filterMedia(null, $all, $link)['visible'];
    }

    private function front(string $path): string
    {
        return config('pawfit.frontend_url').$path;
    }

    private function abs(?string $url): ?string
    {
        if ($url === null) {
            return null;
        }

        return str_starts_with($url, '/') ? $this->front($url) : $url;
    }

    private function encodeCursor(int $offset): string
    {
        return rtrim(strtr(base64_encode("o:{$offset}"), '+/', '-_'), '=');
    }

    private function decodeCursor(string $cursor): int
    {
        if ($cursor === '') {
            return 0;
        }
        $raw = base64_decode(strtr($cursor, '-_', '+/'), true);
        if ($raw === false || ! preg_match('/^o:(\d{1,6})$/', $raw, $m)) {
            abort(422, 'Invalid cursor.');
        }

        return (int) $m[1];
    }
}
