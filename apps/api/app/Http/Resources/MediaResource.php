<?php

namespace App\Http\Resources;

use App\Models\Fursona;
use App\Models\Media;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MediaResource extends JsonResource
{
    /** @var Media */
    public $resource;

    public function toArray(Request $request): array
    {
        $m = $this->resource;
        $slug = $request->attributes->get('share_slug');
        $kit = $request->attributes->get('kit_slug');
        $isOwner = $request->user('sanctum')?->id === $m->owner_id;

        return [
            'id' => $m->id,
            'fursona_id' => $m->fursona_id,
            'kind' => $m->kind,
            'caption' => $m->caption,
            'credit_name' => $m->credit_name,
            'credit_url' => $m->credit_url,
            'is_nsfw' => $m->is_nsfw,
            'origin' => $m->origin ?? 'upload',
            'is_head_sticker' => (bool) $m->is_head_sticker,
            'visibility_override' => $m->visibility_override,
            'sort_order' => $m->sort_order,
            'status' => $m->status,
            'status_note' => $this->when($isOwner, $m->status_note),
            'width' => $m->width,
            'height' => $m->height,
            'bytes' => $this->when($isOwner, $m->bytes),
            // 由 Visibility::filterMedia 設定；擁有者介面沒有這個值時前端視為 show
            'state' => $m->getAttribute('view_state') ?? 'show',
            'urls' => [
                'thumb' => static::imgUrl($m, 'thumb', $slug, $kit),
                'display' => static::imgUrl($m, 'display', $slug, $kit),
                'original' => $this->when($isOwner, static::imgUrl($m, 'original')),
            ],
            'created_at' => $m->created_at,
        ];
    }

    /** 圖片一律走 ACL 路由，由後端 302 到簽名 URL。 */
    public static function imgUrl(Media $m, string $variant, ?string $slug = null, ?string $kitSlug = null, ?string $postId = null): string
    {
        $url = "/api/img/{$m->id}?v={$variant}";
        if ($slug) {
            $url .= '&s='.urlencode($slug);
        }
        if ($kitSlug) {
            $url .= '&k='.urlencode($kitSlug);
        }
        if ($postId) {
            $url .= '&p='.urlencode($postId);
        }

        return $url;
    }

    public static function avatarUrl(User|Fursona $subject): ?string
    {
        if ($subject->avatar_media_id) {
            $media = $subject->relationLoaded('avatarMedia') ? $subject->avatarMedia : $subject->avatarMedia()->first();
            if ($media && $media->isActive()) {
                return static::imgUrl($media, 'thumb');
            }
        }

        return $subject instanceof User ? $subject->google_avatar_url : null;
    }
}
