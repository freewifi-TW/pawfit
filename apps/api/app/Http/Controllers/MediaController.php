<?php

namespace App\Http\Controllers;

use App\Http\Resources\MediaResource;
use App\Http\Resources\PublicUserResource;
use App\Jobs\ProcessMedia;
use App\Models\Fursona;
use App\Models\Media;
use App\Services\MediaStorage;
use App\Services\Visibility;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * 上傳流程（SASD §2.1）：presign → 瀏覽器直傳原檔 → confirm 寫 DB（processing）→ queue 產衍生版 → active。
 */
class MediaController extends Controller
{
    public function __construct(private readonly MediaStorage $storage) {}

    public function presign(Request $request): JsonResponse
    {
        $data = $request->validate([
            'fursona_id' => ['required', 'uuid'],
            'content_type' => ['required', Rule::in(config('pawfit.media.allowed_mimes'))],
            'bytes' => ['required', 'integer', 'min:1', 'max:'.config('pawfit.quota.max_file_bytes')],
        ], [
            'content_type.in' => __('messages.media.content_type_not_allowed'),
            'bytes.max' => __('messages.media.file_too_large', ['max' => $this->maxFileMb()]),
        ]);

        $fursona = $this->ownedFursona($request, $data['fursona_id']);
        $user = $request->user();

        if ($user->uploadsToday() >= config('pawfit.quota.daily_uploads')) {
            throw ValidationException::withMessages(['bytes' => __('messages.media.daily_limit_reached')]);
        }
        if ($user->storageUsedBytes() + $data['bytes'] > config('pawfit.quota.storage_bytes')) {
            throw ValidationException::withMessages(['bytes' => __('messages.media.storage_full')]);
        }

        $key = $this->storage->newOriginalKey($fursona, $data['content_type']);
        $presigned = $this->storage->presignedPut($key, $data['content_type']);

        return response()->json([
            'storage_key' => $key,
            'upload_url' => $presigned['url'],
            'headers' => $presigned['headers'],
        ]);
    }

    public function confirm(Request $request): JsonResponse
    {
        $data = $request->validate([
            'fursona_id' => ['required', 'uuid'],
            'storage_key' => ['required', 'string', 'max:300'],
            'kind' => ['required', Rule::in(Media::KINDS)],
            'is_nsfw' => ['required', 'boolean'],
            'caption' => ['nullable', 'string', 'max:200'],
            'credit_name' => ['nullable', 'string', 'max:80'],
            'credit_url' => ['nullable', 'url', 'max:300'],
            'visibility_override' => ['nullable', Rule::in(Fursona::allowedVisibilities())],
            // Phase 2 FR-B8.4：換獸頭工具輸出標記來源（只有 flag 開啟時接受）
            'origin' => ['sometimes', Rule::in(config('pawfit.features.head_sticker') ? Media::ORIGINS : ['upload'])],
        ], [
            'kind.required' => __('messages.media.kind_required'),
            'is_nsfw.required' => __('messages.media.is_nsfw_required'),
        ]);

        $fursona = $this->ownedFursona($request, $data['fursona_id']);

        if (! $this->storage->belongsTo($data['storage_key'], $fursona)) {
            throw ValidationException::withMessages(['storage_key' => __('messages.media.invalid_storage_key')]);
        }
        if (Media::where('storage_key', $data['storage_key'])->exists()) {
            throw ValidationException::withMessages(['storage_key' => __('messages.media.already_confirmed')]);
        }
        if (! $this->storage->exists($data['storage_key'])) {
            throw ValidationException::withMessages(['storage_key' => __('messages.media.upload_not_found')]);
        }

        $bytes = $this->storage->size($data['storage_key']);
        if ($bytes > config('pawfit.quota.max_file_bytes')) {
            throw ValidationException::withMessages(['storage_key' => __('messages.media.file_exceeds_limit', ['max' => $this->maxFileMb()])]);
        }

        $media = $fursona->media()->create([
            'owner_id' => $fursona->owner_id,
            'kind' => $data['kind'],
            'storage_key' => $data['storage_key'],
            'bytes' => $bytes,
            'is_nsfw' => $data['is_nsfw'],
            'caption' => $data['caption'] ?? null,
            'credit_name' => $data['credit_name'] ?? null,
            'credit_url' => $data['credit_url'] ?? null,
            'visibility_override' => $data['visibility_override'] ?? null,
            'origin' => $data['origin'] ?? 'upload',
            'sort_order' => ((int) $fursona->media()->max('sort_order')) + 1,
            'status' => 'processing',
        ]);

        ProcessMedia::dispatch($media);

        return (new MediaResource($media))->response()->setStatusCode(201);
    }

    public function update(Request $request, Media $media): MediaResource
    {
        $this->authorize('update', $media);

        $data = $request->validate([
            'kind' => ['sometimes', Rule::in(Media::KINDS)],
            'is_nsfw' => ['sometimes', 'boolean'],
            'caption' => ['sometimes', 'nullable', 'string', 'max:200'],
            'credit_name' => ['sometimes', 'nullable', 'string', 'max:80'],
            'credit_url' => ['sometimes', 'nullable', 'url', 'max:300'],
            'visibility_override' => ['sometimes', 'nullable', Rule::in(Fursona::allowedVisibilities())],
            'is_head_sticker' => ['sometimes', 'boolean'],
        ]);

        // FR-B8.2：貼圖素材只允許 2D 且 SFW（改成 NSFW 或非 2D 時自動取消標記）
        $kind = $data['kind'] ?? $media->kind;
        $nsfw = $data['is_nsfw'] ?? $media->is_nsfw;
        if (! empty($data['is_head_sticker'])) {
            if (! config('pawfit.features.head_sticker')) {
                throw ValidationException::withMessages(['is_head_sticker' => __('messages.media.sticker_disabled')]);
            }
            if ($kind !== 'art2d' || $nsfw) {
                throw ValidationException::withMessages(['is_head_sticker' => __('messages.media.sticker_requires_sfw_art2d')]);
            }
        } elseif (! array_key_exists('is_head_sticker', $data) && $media->is_head_sticker && ($kind !== 'art2d' || $nsfw)) {
            $data['is_head_sticker'] = false;
        }

        $media->fill($data)->save();

        return new MediaResource($media->fresh());
    }

    /**
     * GET /api/media/stickers：換獸頭工具可用的貼圖素材（FR-B8.2／B8.3）。
     * 自己的全部 + 好友的（該素材對好友可見：public／friends；含獸設與單圖隱私），一律 SFW、active。
     */
    public function stickers(Request $request, Visibility $visibility): JsonResponse
    {
        $me = $request->user();
        $friendIds = $me->friendIds();

        $rows = Media::with(['fursona.owner', 'owner'])
            ->where('is_head_sticker', true)->where('status', 'active')->where('kind', 'art2d')->where('is_nsfw', false)
            ->where(fn ($q) => $q->where('owner_id', $me->id)->orWhereIn('owner_id', $friendIds))
            ->orderBy('owner_id')->orderBy('sort_order')
            ->get()
            ->filter(fn (Media $m) => $m->owner_id === $me->id || $visibility->mediaState($me, $m) === 'show')
            ->values();

        return response()->json([
            'mine' => MediaResource::collection($rows->where('owner_id', $me->id)->values()),
            'friends' => $rows->where('owner_id', '!=', $me->id)->groupBy('owner_id')->map(fn ($group) => [
                'user' => new PublicUserResource($group->first()->owner),
                'media' => MediaResource::collection($group->values()),
            ])->values(),
        ]);
    }

    public function destroy(Request $request, Media $media): JsonResponse
    {
        $this->authorize('delete', $media);

        $this->storage->deleteAll($media);
        $media->delete();

        return response()->json(null, 204);
    }

    /** 上傳失敗時由前端呼叫，清掉已直傳但未 confirm 的原檔。 */
    public function abandon(Request $request): JsonResponse
    {
        $data = $request->validate(['fursona_id' => ['required', 'uuid'], 'storage_key' => ['required', 'string', 'max:300']]);
        $fursona = $this->ownedFursona($request, $data['fursona_id']);

        if ($this->storage->belongsTo($data['storage_key'], $fursona) && ! Media::where('storage_key', $data['storage_key'])->exists()) {
            Storage::disk('s3')->delete($data['storage_key']);
        }

        return response()->json(null, 204);
    }

    private function ownedFursona(Request $request, string $id): Fursona
    {
        $fursona = Fursona::where('owner_id', $request->user()->id)->find($id);
        if (! $fursona) {
            throw ValidationException::withMessages(['fursona_id' => __('messages.fursona.not_found')]);
        }

        return $fursona;
    }

    /** 單檔上限（MB），給訊息的 :max 用。 */
    private function maxFileMb(): int
    {
        return (int) round(config('pawfit.quota.max_file_bytes') / 1048576);
    }
}
