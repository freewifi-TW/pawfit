<?php

namespace App\Http\Resources;

use App\Models\Fursona;
use App\Models\Media;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FursonaResource extends JsonResource
{
    /** @var Fursona */
    public $resource;

    public function toArray(Request $request): array
    {
        $f = $this->resource;
        $isOwner = $request->user('sanctum')?->id === $f->owner_id;
        $slug = $request->attributes->get('share_slug');

        // media 可能是擁有者的完整清單，或是 Visibility::filterMedia 過濾後的結果（由 controller 塞進 relation）
        $media = $f->relationLoaded('media') ? $f->media : null;
        $cover = $media?->first(fn (Media $m) => $m->isActive() && ! $m->isNsfwContent());

        return [
            'id' => $f->id,
            'name' => $f->name,
            'species' => $f->species,
            'bio' => $f->bio,
            'tags' => $f->tags ?? [],
            'palette' => $f->palette ?? [],
            'visibility' => $f->visibility,
            'is_nsfw' => $f->is_nsfw,
            'is_representative' => $f->is_representative,
            'avatar_media_id' => $f->avatar_media_id,
            'avatar_url' => MediaResource::avatarUrl($f),
            'cover_url' => $cover ? MediaResource::imgUrl($cover, 'display', $slug) : null,
            'media_count' => $f->media_count ?? $media?->count(),
            'palette_count' => count($f->palette ?? []),
            'removed_at' => $this->when($isOwner, $f->removed_at),
            // 嵌入開關（FR-7.1）：null＝繼承用戶總開關；embed_enabled 為實際生效值
            'allow_embed_api' => $this->when($isOwner, $f->allow_embed_api),
            'embed_enabled' => $this->when($isOwner, fn () => $f->embedEnabled()),
            'share_link' => $this->when(
                $isOwner,
                fn () => $f->shareLink ? new ShareLinkResource($f->shareLink) : null,
            ),
            'media' => $this->when($media !== null, fn () => MediaResource::collection($media)),
            'owner' => $this->whenLoaded('owner', fn () => new PublicUserResource($f->owner)),
            'created_at' => $f->created_at,
            'updated_at' => $f->updated_at,
        ];
    }
}
