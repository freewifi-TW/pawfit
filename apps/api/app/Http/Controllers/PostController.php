<?php

namespace App\Http\Controllers;

use App\Http\Resources\PostResource;
use App\Models\Fursona;
use App\Models\Media;
use App\Models\Post;
use App\Services\Visibility;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** 貼文 CRUD（Phase 2 FR-B3、FR-B4）。可見性一律經 Visibility::postState。 */
class PostController extends Controller
{
    public function __construct(private readonly Visibility $visibility) {}

    /** GET /api/posts/{post} */
    public function show(Request $request, Post $post): PostResource
    {
        $viewer = $request->user('sanctum');
        $post->load(['author', 'fursona', 'media.fursona.owner']);
        $state = $this->visibility->postState($viewer, $post);
        abort_if($state === null, 404);
        $post->setAttribute('liked_by_me', $viewer ? in_array($post->id, $viewer->likedPostIds([$post->id]), true) : false);

        return new PostResource($this->visibility->decoratePost($viewer, $post, $state));
    }

    /** POST /api/posts */
    public function store(Request $request): JsonResponse
    {
        $me = $request->user();
        $data = $this->validated($request);

        $fursona = null;
        if (! empty($data['fursona_id'])) {
            $fursona = Fursona::where('owner_id', $me->id)->whereNull('removed_at')->find($data['fursona_id']);
            if (! $fursona) {
                throw ValidationException::withMessages(['fursona_id' => __('messages.posts.fursona_not_owned')]);
            }
        }

        $ids = array_values($data['media_ids']);
        $found = Media::where('owner_id', $me->id)->whereIn('id', $ids)->get()->keyBy('id');
        foreach ($ids as $id) {
            $m = $found->get($id);
            if (! $m) {
                throw ValidationException::withMessages(['media_ids' => __('messages.posts.media_not_owned')]);
            }
            if (! $m->isActive()) {
                throw ValidationException::withMessages(['media_ids' => __('messages.posts.media_not_active')]);
            }
        }
        $mediaList = collect($ids)->map(fn ($id) => $found->get($id));

        // FR-B4.1：任一來源圖或所屬獸設為 NSFW → 貼文鎖定 NSFW
        $locked = $mediaList->contains(fn (Media $m) => $m->is_nsfw || $m->fursona->is_nsfw) || ($fursona?->is_nsfw ?? false);

        $post = DB::transaction(function () use ($me, $fursona, $data, $ids, $locked) {
            $post = Post::create([
                'author_id' => $me->id,
                'fursona_id' => $fursona?->id,
                'body' => $data['body'],
                'tags' => $data['tags'],
                'is_nsfw' => $locked || $data['is_nsfw'],
                'visibility' => $data['visibility'],
                'status' => 'active',
            ]);
            $post->media()->attach(collect($ids)->mapWithKeys(fn ($id, $i) => [$id => ['sort' => $i]])->all());

            return $post;
        });

        $post->load(['author', 'fursona', 'media.fursona.owner']);

        return (new PostResource($this->visibility->decoratePost($me, $post, 'show')))->response()->setStatusCode(201);
    }

    /** PATCH /api/posts/{post}：只能改文字、標籤、隱私；圖片組成不可事後變更（FR-B3.5）。 */
    public function update(Request $request, Post $post): PostResource
    {
        $this->authorize('update', $post);
        $data = $this->validated($request, $post);

        if (array_key_exists('is_nsfw', $data) && ! $data['is_nsfw'] && $this->nsfwLocked($post)) {
            throw ValidationException::withMessages(['is_nsfw' => __('messages.posts.nsfw_locked')]);
        }

        $post->fill($data)->save();
        $post->load(['author', 'fursona', 'media.fursona.owner']);

        return new PostResource($this->visibility->decoratePost($request->user(), $post, 'show'));
    }

    /** DELETE /api/posts/{post} */
    public function destroy(Request $request, Post $post): JsonResponse
    {
        $this->authorize('delete', $post);
        $post->delete();

        return response()->json(null, 204);
    }

    private function validated(Request $request, ?Post $existing = null): array
    {
        $isCreate = $existing === null;
        $data = $request->validate([
            'body' => [$isCreate ? 'required' : 'sometimes', 'string', 'max:2000'],
            'tags' => ['sometimes', 'array', 'max:'.config('pawfit.fursona.max_tags')],
            'tags.*' => ['string', 'min:1', 'max:20'],
            'visibility' => [$isCreate ? 'required' : 'sometimes', Rule::in(Post::VISIBILITIES)],
            'is_nsfw' => [$isCreate ? 'required' : 'sometimes', 'boolean'],
            'fursona_id' => ['sometimes', 'nullable', 'uuid'],
            'media_ids' => [$isCreate ? 'required' : 'prohibited', 'array', 'min:1', 'max:'.Post::MAX_MEDIA],
            'media_ids.*' => ['uuid', 'distinct'],
        ], [
            'media_ids.required' => __('messages.posts.media_required'),
            'media_ids.min' => __('messages.posts.media_required'),
            'media_ids.max' => __('messages.posts.too_many_media', ['max' => Post::MAX_MEDIA]),
            'media_ids.prohibited' => __('messages.posts.media_locked'),
            'is_nsfw.required' => __('messages.posts.is_nsfw_required'),
        ]);

        if ($isCreate || isset($data['tags'])) {
            $data['tags'] = collect($data['tags'] ?? [])->map(fn ($t) => trim($t))->filter()->unique()->values()->all();
        }
        if (isset($data['body'])) {
            $data['body'] = trim($data['body']);
        }
        if ($isCreate) {
            unset($data['fursona_id']);
            $data['fursona_id'] = $request->input('fursona_id');
        } else {
            unset($data['fursona_id'], $data['media_ids']);
        }

        return $data;
    }

    private function nsfwLocked(Post $post): bool
    {
        return $post->media()->with('fursona')->get()->contains(fn (Media $m) => $m->is_nsfw || $m->fursona->is_nsfw)
            || ($post->fursona?->is_nsfw ?? false);
    }
}
