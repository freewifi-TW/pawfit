<?php

namespace App\Http\Controllers;

use App\Http\Resources\FursonaResource;
use App\Models\Fursona;
use App\Models\Media;
use App\Services\MediaStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class FursonaController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $fursonas = $request->user()->fursonas()
            ->with(['shareLink', 'avatarMedia', 'media' => fn ($q) => $q->where('status', '!=', 'removed')])
            ->withCount(['media' => fn ($q) => $q->where('status', '!=', 'removed')])
            ->orderByDesc('is_representative')
            ->orderBy('created_at')
            ->get();

        return FursonaResource::collection($fursonas);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $user = $request->user();

        $fursona = DB::transaction(function () use ($user, $data) {
            // 第一隻自動成為代表獸設
            $isFirst = ! $user->fursonas()->exists();
            if (($data['is_representative'] ?? false) || $isFirst) {
                $user->fursonas()->update(['is_representative' => false]);
                $data['is_representative'] = true;
            }

            return $user->fursonas()->create($data)->refresh();
        });

        return (new FursonaResource($fursona->load(['shareLink', 'media'])))->response()->setStatusCode(201);
    }

    public function show(Request $request, Fursona $fursona): FursonaResource
    {
        $this->authorize('view', $fursona);

        $fursona->load(['shareLink', 'avatarMedia', 'owner', 'media' => fn ($q) => $q->where('status', '!=', 'removed')]);

        return new FursonaResource($fursona);
    }

    public function update(Request $request, Fursona $fursona): FursonaResource
    {
        $this->authorize('update', $fursona);
        $data = $this->validated($request, $fursona);

        DB::transaction(function () use ($fursona, $data) {
            if (! empty($data['is_representative'])) {
                $fursona->owner->fursonas()->where('id', '!=', $fursona->id)->update(['is_representative' => false]);
            }
            $fursona->fill($data)->save();
        });

        return new FursonaResource($fursona->fresh(['shareLink', 'avatarMedia', 'owner', 'media']));
    }

    public function destroy(Request $request, Fursona $fursona, MediaStorage $storage): JsonResponse
    {
        $this->authorize('delete', $fursona);

        foreach ($fursona->media as $media) {
            $storage->deleteAll($media);
        }
        $fursona->delete();

        return response()->json(null, 204);
    }

    /** 圖庫拖曳排序。 */
    public function reorderMedia(Request $request, Fursona $fursona): JsonResponse
    {
        $this->authorize('update', $fursona);
        $data = $request->validate(['ids' => ['required', 'array', 'max:500'], 'ids.*' => ['uuid', 'distinct']]);

        $owned = $fursona->media()->pluck('id')->all();
        if (array_diff($data['ids'], $owned)) {
            throw ValidationException::withMessages(['ids' => __('messages.fursona.media_not_owned')]);
        }

        DB::transaction(function () use ($data) {
            foreach ($data['ids'] as $i => $id) {
                Media::where('id', $id)->update(['sort_order' => $i]);
            }
        });

        return response()->json(['ok' => true]);
    }

    private function validated(Request $request, ?Fursona $existing = null): array
    {
        $isCreate = $existing === null;

        $data = $request->validate([
            'name' => [$isCreate ? 'required' : 'sometimes', 'string', 'min:1', 'max:60'],
            'species' => ['sometimes', 'nullable', 'string', 'max:60'],
            'bio' => ['sometimes', 'nullable', 'string', 'max:4000'],
            'tags' => ['sometimes', 'array', 'max:'.config('pawfit.fursona.max_tags')],
            'tags.*' => ['string', 'min:1', 'max:20'],
            'palette' => ['sometimes', 'array', 'max:'.config('pawfit.fursona.max_palette')],
            'palette.*.hex' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'palette.*.name' => ['nullable', 'string', 'max:40'],
            'palette.*.note' => ['nullable', 'string', 'max:80'],
            'visibility' => ['sometimes', Rule::in(Fursona::allowedVisibilities())],
            'is_nsfw' => ['sometimes', 'boolean'],
            'is_representative' => ['sometimes', 'boolean'],
            'avatar_media_id' => ['sometimes', 'nullable', 'uuid'],
            'allow_embed_api' => ['sometimes', 'nullable', 'boolean'],
        ], [
            'palette.*.hex.regex' => __('messages.fursona.palette_hex_format'),
        ]);

        if (isset($data['tags'])) {
            $data['tags'] = collect($data['tags'])->map(fn ($t) => trim($t))->filter()->unique()->values()->all();
        }
        if (isset($data['palette'])) {
            $data['palette'] = collect($data['palette'])->values()->map(fn ($p, $i) => [
                'hex' => strtoupper($p['hex']),
                'name' => trim($p['name'] ?? ''),
                'note' => trim($p['note'] ?? ''),
                'sort' => $i,
            ])->all();
        }
        if (array_key_exists('avatar_media_id', $data) && $data['avatar_media_id'] !== null) {
            $ok = $existing && Media::where('fursona_id', $existing->id)->where('status', 'active')->where('id', $data['avatar_media_id'])->exists();
            if (! $ok) {
                throw ValidationException::withMessages(['avatar_media_id' => __('messages.fursona.avatar_not_in_gallery')]);
            }
        }

        return $data;
    }
}
