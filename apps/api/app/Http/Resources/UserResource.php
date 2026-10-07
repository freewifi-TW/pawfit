<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** 登入者本人看到的完整資料。 */
class UserResource extends JsonResource
{
    /** @var User */
    public $resource;

    public function toArray(Request $request): array
    {
        $u = $this->resource;

        return [
            'id' => $u->id,
            'email' => $u->email,
            'pawfit_id' => $u->pawfit_id,
            'display_name' => $u->display_name ?? $u->name,
            'avatar_url' => MediaResource::avatarUrl($u),
            'locale' => $u->locale,
            'nsfw_pref' => $u->nsfw_pref,
            'effective_nsfw_pref' => $u->effectiveNsfwPref(),
            'adult_confirmed_at' => $u->adult_confirmed_at,
            'tos_accepted_at' => $u->tos_accepted_at,
            'is_onboarded' => $u->isOnboarded(),
            'is_admin' => $u->isAdmin(),
            'is_banned' => $u->is_banned,
            'allow_embed_api' => (bool) $u->allow_embed_api,
            'quota' => [
                'storage_used' => $u->storageUsedBytes(),
                'storage_limit' => config('pawfit.quota.storage_bytes'),
                'uploads_today' => $u->uploadsToday(),
                'daily_limit' => config('pawfit.quota.daily_uploads'),
                'max_file_bytes' => config('pawfit.quota.max_file_bytes'),
            ],
            'created_at' => $u->created_at,
        ];
    }
}
