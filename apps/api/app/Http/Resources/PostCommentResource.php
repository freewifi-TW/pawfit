<?php

namespace App\Http\Resources;

use App\Models\PostComment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PostCommentResource extends JsonResource
{
    /** @var PostComment */
    public $resource;

    public function toArray(Request $request): array
    {
        $c = $this->resource;
        $viewer = $request->user('sanctum');
        $postAuthorId = $c->relationLoaded('post') ? $c->post?->author_id : null;

        return [
            'id' => $c->id,
            'post_id' => $c->post_id,
            'author' => $this->whenLoaded('author', fn () => new PublicUserResource($c->author)),
            'body' => $c->body,
            'status' => $c->status,
            'can_delete' => $viewer !== null && ($viewer->id === $c->author_id || $viewer->id === $postAuthorId || $viewer->isAdmin()),
            'created_at' => $c->created_at,
        ];
    }
}
