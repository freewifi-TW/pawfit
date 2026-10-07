<?php

namespace App\Http\Controllers;

use App\Http\Resources\CommissionKitResource;
use App\Jobs\GenerateCommissionSheet;
use App\Models\CommissionKit;
use App\Models\Fursona;
use App\Models\Media;
use App\Services\CommissionBrief;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

/** 委託需求單——擁有者管理（FR-6.1、FR-6.5）。公開頁見 CommissionPageController。 */
class CommissionKitController extends Controller
{
    public function __construct(private readonly CommissionBrief $brief) {}

    /** GET /api/commission-kits?fursona_id= */
    public function index(Request $request): AnonymousResourceCollection
    {
        $q = CommissionKit::where('owner_id', $request->user()->id)->latest();
        if ($request->filled('fursona_id')) {
            $q->where('fursona_id', $request->query('fursona_id'));
        }

        return CommissionKitResource::collection($q->limit(100)->get());
    }

    /** POST /api/commission-kits */
    public function store(Request $request): JsonResponse
    {
        $max = (int) config('pawfit.commission.max_media');
        $data = $request->validate([
            'fursona_id' => ['required', 'uuid'],
            'media_ids' => ['required', 'array', 'min:1', "max:{$max}"],
            'media_ids.*' => ['uuid', 'distinct'],
            'request' => ['sometimes', 'array'],
            'request.composition' => ['nullable', 'string', 'max:500'],
            'request.scene' => ['nullable', 'string', 'max:500'],
            'request.size' => ['nullable', 'string', 'max:200'],
            'request.usage' => ['nullable', 'string', 'max:200'],
            'request.budget' => ['nullable', 'string', 'max:100'],
            'request.deadline' => ['nullable', 'string', 'max:100'],
            'request.notes' => ['nullable', 'string', 'max:2000'],
        ], [
            'media_ids.required' => __('messages.commission.media_required'),
            'media_ids.min' => __('messages.commission.media_required'),
            'media_ids.max' => __('messages.commission.too_many_media', ['max' => $max]),
        ]);

        $fursona = Fursona::with('owner')->where('owner_id', $request->user()->id)->find($data['fursona_id']);
        if (! $fursona || $fursona->isRemoved()) {
            throw ValidationException::withMessages(['fursona_id' => __('messages.commission.fursona_not_owned')]);
        }

        $ids = array_values($data['media_ids']);
        $found = Media::where('fursona_id', $fursona->id)->whereIn('id', $ids)->get()->keyBy('id');
        foreach ($ids as $id) {
            $m = $found->get($id);
            if (! $m) {
                throw ValidationException::withMessages(['media_ids' => __('messages.commission.media_not_owned')]);
            }
            if (! $m->isActive()) {
                throw ValidationException::withMessages(['media_ids' => __('messages.commission.media_not_active')]);
            }
        }
        $media = collect($ids)->map(fn ($id) => $found->get($id));

        $requestFields = [];
        foreach (CommissionKit::REQUEST_FIELDS as $field) {
            $v = $data['request'][$field] ?? null;
            $requestFields[$field] = filled($v) ? trim((string) $v) : null;
        }

        $kit = new CommissionKit([
            'fursona_id' => $fursona->id,
            'owner_id' => $fursona->owner_id,
            'kind' => 'art2d',
            'slug' => CommissionKit::generateSlug(),
            'media_ids' => $ids,
            'is_nsfw' => $fursona->is_nsfw || $media->contains(fn (Media $m) => $m->is_nsfw),
            'brief_source' => 'template',
            'brief_text' => [],
            'status' => 'processing',
            'snapshot' => [
                'fursona' => [
                    'name' => $fursona->name,
                    'species' => $fursona->species,
                    'bio' => $fursona->bio,
                    'tags' => $fursona->tags ?? [],
                    'palette' => collect($fursona->palette ?? [])->map(fn ($p) => [
                        'hex' => $p['hex'] ?? '', 'name' => $p['name'] ?? '', 'note' => $p['note'] ?? '',
                    ])->values()->all(),
                ],
                'owner' => [
                    'pawfit_id' => $fursona->owner->pawfit_id,
                    'display_name' => $fursona->owner->display_name ?? $fursona->owner->name,
                ],
                'media' => $media->map(fn (Media $m) => [
                    'id' => $m->id, 'kind' => $m->kind, 'caption' => $m->caption,
                    'credit_name' => $m->credit_name, 'credit_url' => $m->credit_url,
                    'is_nsfw' => $m->is_nsfw, 'width' => $m->width, 'height' => $m->height,
                ])->values()->all(),
                'request' => $requestFields,
                'share_url' => $fursona->visibility === 'private' ? null : $fursona->shareLink?->url(),
            ],
        ]);
        $kit->created_at = now();
        $kit->brief_text = $this->brief->renderAll($kit);
        $kit->save();

        GenerateCommissionSheet::dispatch($kit);

        return (new CommissionKitResource($kit))->response()->setStatusCode(201);
    }

    /** GET /api/commission-kits/{commissionKit} */
    public function show(CommissionKit $commissionKit): CommissionKitResource
    {
        $this->authorize('view', $commissionKit);

        return new CommissionKitResource($commissionKit);
    }

    /** POST /api/commission-kits/{commissionKit}/regenerate：換 slug（舊連結立即失效）並重跑合成圖。 */
    public function regenerate(CommissionKit $commissionKit): CommissionKitResource
    {
        $this->authorize('update', $commissionKit);
        abort_unless($commissionKit->isActive(), 409, __('messages.commission.revoked'));

        $commissionKit->forceFill(['slug' => CommissionKit::generateSlug(), 'status' => 'processing'])->save();
        // slug 變了，文字裡的需求單連結也要跟著換
        $commissionKit->brief_text = $this->brief->renderAll($commissionKit);
        $commissionKit->save();

        GenerateCommissionSheet::dispatch($commissionKit);

        return new CommissionKitResource($commissionKit->fresh());
    }

    /** DELETE /api/commission-kits/{commissionKit}：停用（連結失效、資料保留）。 */
    public function destroy(CommissionKit $commissionKit): JsonResponse
    {
        $this->authorize('delete', $commissionKit);
        $commissionKit->forceFill(['status' => 'revoked', 'revoked_at' => now()])->save();

        return response()->json(null, 204);
    }
}
