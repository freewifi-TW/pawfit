<?php

namespace App\Http\Controllers;

use App\Http\Resources\PostCommentResource;
use App\Models\Post;
use App\Models\PostComment;
use App\Services\Visibility;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** 讚與單層留言（FR-B6）。看不到的貼文一律 404（不洩漏存在）。 */
class PostInteractionController extends Controller
{
    public function __construct(private readonly Visibility $visibility) {}

    /** POST /api/posts/{post}/like */
    public function like(Request $request, Post $post): JsonResponse
    {
        $me = $request->user();
        $this->mustSee($me, $post);

        DB::transaction(function () use ($me, $post) {
            $inserted = DB::table('post_likes')->insertOrIgnore(['post_id' => $post->id, 'user_id' => $me->id, 'created_at' => now()]);
            if ($inserted) {
                $post->increment('like_count');
            }
        });

        return response()->json(['liked' => true, 'like_count' => $post->fresh()->like_count]);
    }

    /** DELETE /api/posts/{post}/like */
    public function unlike(Request $request, Post $post): JsonResponse
    {
        $me = $request->user();
        $this->mustSee($me, $post);

        DB::transaction(function () use ($me, $post) {
            $deleted = DB::table('post_likes')->where('post_id', $post->id)->where('user_id', $me->id)->delete();
            if ($deleted) {
                $post->where('like_count', '>', 0)->decrement('like_count');
            }
        });

        return response()->json(['liked' => false, 'like_count' => $post->fresh()->like_count]);
    }

    /** GET /api/posts/{post}/comments?after=<id>：時間正序，每頁 50。 */
    public function comments(Request $request, Post $post): JsonResponse
    {
        $viewer = $request->user('sanctum');
        $this->mustSee($viewer, $post);

        $q = $post->comments()->with(['author', 'post'])->where('status', 'active')->orderBy('created_at')->orderBy('id');
        if ($viewer) {
            $blocked = $viewer->blockedEitherWayIds();
            if ($blocked) {
                $q->whereNotIn('author_id', $blocked);
            }
        }
        if ($after = $request->query('after')) {
            $anchor = PostComment::find($after);
            if ($anchor) {
                $q->where(fn ($w) => $w->where('created_at', '>', $anchor->created_at)
                    ->orWhere(fn ($x) => $x->where('created_at', $anchor->created_at)->where('id', '>', $anchor->id)));
            }
        }
        $rows = $q->limit(51)->get();
        $hasMore = $rows->count() > 50;
        $rows = $rows->take(50);

        return response()->json([
            'data' => PostCommentResource::collection($rows),
            'next_after' => $hasMore ? $rows->last()?->id : null,
            'total' => $post->comment_count,
        ]);
    }

    /** POST /api/posts/{post}/comments */
    public function storeComment(Request $request, Post $post): JsonResponse
    {
        $me = $request->user();
        $this->mustSee($me, $post);
        $data = $request->validate(['body' => ['required', 'string', 'min:1', 'max:500']]);

        $comment = DB::transaction(function () use ($me, $post, $data) {
            $c = $post->comments()->create(['author_id' => $me->id, 'body' => trim($data['body']), 'status' => 'active']);
            $post->increment('comment_count');

            return $c;
        });

        return (new PostCommentResource($comment->load(['author', 'post'])))->response()->setStatusCode(201);
    }

    /** DELETE /api/comments/{comment}：留言者、貼文作者或管理員。 */
    public function destroyComment(Request $request, PostComment $comment): JsonResponse
    {
        $me = $request->user();
        $post = $comment->post;
        abort_unless($me->id === $comment->author_id || $me->id === $post->author_id || $me->isAdmin(), 403);

        DB::transaction(function () use ($comment, $post) {
            if ($comment->isActive()) {
                $comment->forceFill(['status' => 'removed'])->save();
                $post->where('comment_count', '>', 0)->decrement('comment_count');
            }
        });

        return response()->json(null, 204);
    }

    private function mustSee($viewer, Post $post): void
    {
        $post->loadMissing(['author']);
        abort_if($this->visibility->postState($viewer, $post) === null, 404);
    }
}
