<?php

namespace App\Http\Resources;

use App\Models\CommissionKit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** 委託需求單（FR-6）：擁有者管理介面與公開頁共用；擁有者多拿到 media_ids 與撤銷資訊。 */
class CommissionKitResource extends JsonResource
{
    /** @var CommissionKit */
    public $resource;

    public function toArray(Request $request): array
    {
        $k = $this->resource;
        $isOwner = $request->user('sanctum')?->id === $k->owner_id;

        return [
            'id' => $k->id,
            'slug' => $k->slug,
            'url' => $k->url(),
            'kind' => $k->kind,
            'status' => $k->status,
            'is_nsfw' => $k->is_nsfw,
            'fursona_id' => $k->fursona_id,
            'snapshot' => $k->snapshot,
            'brief_text' => $k->brief_text,
            'brief_source' => $k->brief_source,
            'media_count' => count($k->media_ids ?? []),
            // 合成圖一律經 ACL 路由 302（與圖片相同原則）
            'sheet_url' => $k->sheet_key && $k->status === 'active' ? '/api/commission/'.$k->slug.'/sheet' : null,
            'media_ids' => $this->when($isOwner, $k->media_ids ?? []),
            'revoked_at' => $this->when($isOwner, $k->revoked_at),
            'created_at' => $k->created_at,
            'updated_at' => $k->updated_at,
        ];
    }
}
