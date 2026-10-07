<?php

namespace App\Http\Controllers;

use App\Http\Resources\PostResource;
use App\Models\Post;
use App\Models\User;
use App\Services\FeedCursor;
use App\Services\Visibility;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 雙軌河道（FR-B5）與個人主頁貼文（§2.3）。
 * 最直白的查詢＋索引；NSFW 偏好與封鎖在查詢層先排除，最後仍經 Visibility::postState 把關。
 */
class FeedController extends Controller
{
    public function __construct(private readonly Visibility $visibility, private readonly FeedCursor $cursor) {}

    /** GET /api/feed/friends?cursor=：好友（含自己）的公開與限好友貼文。 */
    public function friends(Request $request): JsonResponse
    {
        $me = $request->user();
        $authors = [...$me->friendIds(), $me->id];

        $q = Post::query()->active()
            ->whereIn('author_id', $authors)
            ->whereIn('visibility', ['public', 'friends']);
        $this->applyViewerFilters($q, $me);

        return $this->page($request, $q, $me);
    }

    /** GET /api/feed/explore?tags=a,b&cursor=：全站公開貼文，標籤 AND 篩選。 */
    public function explore(Request $request): JsonResponse
    {
        $viewer = $request->user('sanctum');
        $tags = collect(explode(',', (string) $request->query('tags', '')))->map(fn ($t) => trim($t))->filter()->unique()->take(5);

        $q = Post::query()->active()->where('visibility', 'public');
        foreach ($tags as $tag) {
            $q->whereJsonContains('tags', $tag);
        }
        $this->applyViewerFilters($q, $viewer);

        return $this->page($request, $q, $viewer, ['tags' => $tags->values()->all()]);
    }

    /** GET /api/users/{pawfitId}/posts?cursor=：個人主頁的貼文分頁。 */
    public function user(Request $request, string $pawfitId): JsonResponse
    {
        $user = User::whereRaw('lower(pawfit_id) = ?', [strtolower($pawfitId)])->first();
        abort_if(! $user || $user->is_banned, 404);
        $viewer = $request->user('sanctum');
        abort_if($this->visibility->isBlockedBetween($viewer, $user->id), 404);

        $isOwner = $viewer?->id === $user->id;
        $friend = $this->visibility->relation($viewer, $user->id)['friend'];

        $q = Post::query()->where('author_id', $user->id);
        if (! $isOwner) {
            $q->active()->whereIn('visibility', $friend ? ['public', 'friends'] : ['public']);
        }
        $this->applyViewerFilters($q, $viewer);

        return $this->page($request, $q, $viewer);
    }

    /** 查詢層先排除：封鎖對象、以及（偏好＝隱藏或訪客時）NSFW 貼文——自己的貼文一律保留。 */
    private function applyViewerFilters(Builder $q, ?User $viewer): void
    {
        if ($viewer) {
            $blocked = $viewer->blockedEitherWayIds();
            if ($blocked) {
                $q->whereNotIn('author_id', $blocked);
            }
        }
        if ($this->visibility->nsfwState($viewer) === null) {
            $q->where(fn (Builder $w) => $w->where('is_nsfw', false)->when($viewer, fn ($x) => $x->orWhere('author_id', $viewer->id)));
        }
    }

    private function page(Request $request, Builder $q, ?User $viewer, array $extra = []): JsonResponse
    {
        $rows = $this->cursor->apply($q->with(['author', 'fursona', 'media.fursona.owner']), $request->query('cursor'))->get();
        $hasMore = $rows->count() > FeedCursor::PAGE;
        $rows = $rows->take(FeedCursor::PAGE);

        $likedIds = $viewer && $rows->isNotEmpty()
            ? $viewer->likedPostIds($rows->pluck('id')->all())
            : [];

        $posts = $rows->map(function (Post $p) use ($viewer, $likedIds) {
            $state = $this->visibility->postState($viewer, $p);
            if ($state === null) {
                return null;
            }
            $p->setAttribute('liked_by_me', in_array($p->id, $likedIds, true));

            return $this->visibility->decoratePost($viewer, $p, $state);
        })->filter()->values();

        $last = $rows->last();

        return response()->json($extra + [
            'data' => PostResource::collection($posts),
            'next_cursor' => $hasMore && $last ? $this->cursor->encode($last->created_at, $last->id) : null,
        ]);
    }
}
