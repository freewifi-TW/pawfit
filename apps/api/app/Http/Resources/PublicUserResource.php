<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** 對外公開的用戶摘要（個人主頁、分享頁的擁有者）。 */
class PublicUserResource extends JsonResource
{
    /** @var User */
    public $resource;

    public function toArray(Request $request): array
    {
        $u = $this->resource;

        return [
            'id' => $u->id, // 檢舉用戶時需要
            'pawfit_id' => $u->pawfit_id,
            'display_name' => $u->display_name ?? $u->name,
            'avatar_url' => MediaResource::avatarUrl($u),
            'joined_at' => $u->created_at,
        ];
    }
}
