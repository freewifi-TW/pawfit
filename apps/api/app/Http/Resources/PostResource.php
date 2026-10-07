<?php

namespace App\Http\Resources;

use App\Models\Media;
use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 貼文（FR-B3、FR-B5.4）。
 * 需由 controller 先載入 author / fursona / media，並以 Visibility::filterPostMedia 設定每張圖的 view_state；
 * 貼文本身的 state（show / blur）與 liked_by_me 也由 controller 以 setAttribute 塞入。
 */
class PostResource extends JsonResource
{
    /** @var Post */
    public $resource;

    public function toArray(Request $request): array
    {
        $p = $this->resource;
        $viewer = $request->user('sanctum');
        $isAuthor = $viewer?->id === $p->author_id;

        return [
            'id' => $p->id,
            'url' => $p->url(),
            'author' => $this->whenLoaded('author', fn () => new PublicUserResource($p->author)),
            'fursona' => $this->whenLoaded('fursona', fn () => $p->fursona ? [
                'id' => $p->fursona->id,
                'name' => $p->fursona->name,
                'species' => $p->fursona->species,
                'avatar_url' => MediaResource::avatarUrl($p->fursona),
            ] : null),
            'body' => $p->body,
            'tags' => $p->tags ?? [],
            'is_nsfw' => $p->is_nsfw,
            'visibility' => $p->visibility,
            'status' => $p->status,
            'status_note' => $this->when($isAuthor || ($viewer?->isAdmin() ?? false), $p->status_note),
            'like_count' => $p->like_count,
            'comment_count' => $p->comment_count,
            'liked_by_me' => (bool) ($p->getAttribute('liked_by_me') ?? false),
            'state' => $p->getAttribute('view_state') ?? 'show',
            'media' => $this->whenLoaded('media', fn () => $p->media->map(fn (Media $m) => [
                'id' => $m->id,
                'kind' => $m->kind,
                'caption' => $m->caption,
                'credit_name' => $m->credit_name,
                'credit_url' => $m->credit_url,
                'is_nsfw' => $m->is_nsfw,
                'width' => $m->width,
                'height' => $m->height,
                'state' => $m->getAttribute('view_state') ?? 'show',
                // 圖片經貼文脈絡出口（p=）：貼文可見即可看圖，不再另判圖庫隱私
                'urls' => [
                    'thumb' => MediaResource::imgUrl($m, 'thumb', postId: $p->id),
                    'display' => MediaResource::imgUrl($m, 'display', postId: $p->id),
                ],
            ])->values()),
            'can_edit' => $isAuthor,
            'created_at' => $p->created_at,
            'updated_at' => $p->updated_at,
        ];
    }
}
