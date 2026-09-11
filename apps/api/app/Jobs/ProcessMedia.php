<?php

namespace App\Jobs;

use App\Models\Media;
use App\Services\ImageProcessor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/** 上傳確認後：產出展示版與縮圖；若分享連結已開浮水印則一併產生浮水印版。 */
class ProcessMedia implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 120;

    public function __construct(public Media $media) {}

    public function handle(ImageProcessor $processor): void
    {
        $media = $this->media->fresh(['fursona.owner', 'fursona.shareLink']);
        if (! $media || $media->status === 'removed') {
            return;
        }

        $processor->generateDerivatives($media);

        if ($media->fursona->shareLink?->watermark) {
            $processor->generateWatermark($media, GenerateWatermarks::label($media->fursona));
        }
    }

    public function failed(?Throwable $e): void
    {
        Log::warning('media processing failed', ['media' => $this->media->id, 'error' => $e?->getMessage()]);
        $this->media->forceFill([
            'status' => 'failed',
            'status_note' => mb_substr($e?->getMessage() ?? 'unknown', 0, 500),
        ])->save();
    }
}
