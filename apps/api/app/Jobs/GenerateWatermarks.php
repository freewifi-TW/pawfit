<?php

namespace App\Jobs;

use App\Models\Fursona;
use App\Services\ImageProcessor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/** 分享連結開啟浮水印時，替該獸設所有還沒有浮水印版的圖產生。 */
class GenerateWatermarks implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    public function __construct(public Fursona $fursona) {}

    public function handle(ImageProcessor $processor): void
    {
        $fursona = $this->fursona->fresh(['owner']);
        if (! $fursona) {
            return;
        }
        $label = static::label($fursona);

        $fursona->media()->where('status', 'active')->whereNull('watermarked_key')->each(function ($media) use ($processor, $label) {
            try {
                $processor->generateWatermark($media, $label);
            } catch (Throwable $e) {
                Log::warning('watermark failed', ['media' => $media->id, 'error' => $e->getMessage()]);
            }
        });
    }

    public static function label(Fursona $fursona): string
    {
        $owner = $fursona->owner;

        return 'pawfit · @'.($owner?->pawfit_id ?? 'pawfit');
    }
}
