<?php

namespace App\Http\Controllers;

use App\Http\Resources\ShareLinkResource;
use App\Jobs\GenerateWatermarks;
use App\Models\Fursona;
use App\Models\ShareLink;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ShareLinkController extends Controller
{
    /** 建立或重新生成：舊連結立即失效（FR-4.4）。 */
    public function store(Request $request, Fursona $fursona): JsonResponse
    {
        $this->authorize('update', $fursona);
        $data = $request->validate(['watermark' => ['sometimes', 'boolean']]);

        $link = DB::transaction(function () use ($fursona, $data) {
            $previous = $fursona->shareLink;
            $fursona->shareLinks()->whereNull('revoked_at')->update(['revoked_at' => now()]);

            return $fursona->shareLinks()->create([
                'slug' => ShareLink::generateSlug(),
                'watermark' => $data['watermark'] ?? $previous?->watermark ?? false,
            ]);
        });

        if ($link->watermark) {
            GenerateWatermarks::dispatch($fursona);
        }

        return (new ShareLinkResource($link))->response()->setStatusCode(201);
    }

    public function update(Request $request, ShareLink $shareLink): ShareLinkResource
    {
        $this->authorize('update', $shareLink);
        $data = $request->validate(['watermark' => ['required', 'boolean']]);

        $wasOff = ! $shareLink->watermark;
        $shareLink->fill($data)->save();

        if ($wasOff && $shareLink->watermark) {
            GenerateWatermarks::dispatch($shareLink->fursona);
        }

        return new ShareLinkResource($shareLink);
    }

    public function destroy(Request $request, ShareLink $shareLink): JsonResponse
    {
        $this->authorize('delete', $shareLink);
        $shareLink->forceFill(['revoked_at' => now()])->save();

        return response()->json(null, 204);
    }
}
