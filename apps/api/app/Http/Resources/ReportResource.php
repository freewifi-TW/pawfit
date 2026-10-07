<?php

namespace App\Http\Resources;

use App\Models\Fursona;
use App\Models\Media;
use App\Models\Post;
use App\Models\PostComment;
use App\Models\Report;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReportResource extends JsonResource
{
    /** @var Report */
    public $resource;

    public function toArray(Request $request): array
    {
        $r = $this->resource;

        return [
            'id' => $r->id,
            'target_type' => $r->target_type,
            'target_id' => $r->target_id,
            'target' => $this->targetSummary($r),
            'reason_code' => $r->reason_code,
            'detail' => $r->detail,
            'status' => $r->status,
            'reporter' => $r->reporter ? new PublicUserResource($r->reporter) : null,
            'resolved_by' => $r->resolver ? new PublicUserResource($r->resolver) : null,
            'resolved_at' => $r->resolved_at,
            'created_at' => $r->created_at,
        ];
    }

    /** 給後台列表用的目標摘要；目標已被刪除時回 null。 */
    private function targetSummary(Report $r): ?array
    {
        return match ($r->target_type) {
            'media' => ($m = Media::with('fursona.owner')->find($r->target_id)) ? [
                'label' => $m->caption ?: __('messages.report.untitled_media'),
                'fursona_name' => $m->fursona?->name,
                'fursona_id' => $m->fursona_id,
                'owner_pawfit_id' => $m->fursona?->owner?->pawfit_id,
                'is_nsfw' => $m->is_nsfw,
                'status' => $m->status,
                'thumb_url' => MediaResource::imgUrl($m, 'thumb'),
            ] : null,
            'fursona' => ($f = Fursona::with('owner')->find($r->target_id)) ? [
                'label' => $f->name,
                'fursona_id' => $f->id,
                'owner_pawfit_id' => $f->owner?->pawfit_id,
                'visibility' => $f->visibility,
                'removed_at' => $f->removed_at,
            ] : null,
            'profile' => ($u = User::find($r->target_id)) ? [
                'label' => '@'.($u->pawfit_id ?? __('messages.report.no_pawfit_id')),
                'owner_pawfit_id' => $u->pawfit_id,
                'is_banned' => $u->is_banned,
            ] : null,
            'post' => ($p = Post::with(['author', 'media'])->find($r->target_id)) ? [
                'label' => mb_strimwidth($p->body !== '' ? $p->body : __('messages.report.untitled_post'), 0, 60, '…'),
                'owner_pawfit_id' => $p->author?->pawfit_id,
                'is_nsfw' => $p->is_nsfw,
                'status' => $p->status,
                'thumb_url' => ($first = $p->media->first()) ? MediaResource::imgUrl($first, 'thumb', postId: $p->id) : null,
                'post_id' => $p->id,
            ] : null,
            'comment' => ($c = PostComment::with('author')->find($r->target_id)) ? [
                'label' => mb_strimwidth($c->body, 0, 60, '…'),
                'owner_pawfit_id' => $c->author?->pawfit_id,
                'status' => $c->status,
                'post_id' => $c->post_id,
            ] : null,
            default => null,
        };
    }
}
