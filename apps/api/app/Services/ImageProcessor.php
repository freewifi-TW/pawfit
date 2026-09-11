<?php

namespace App\Services;

use App\Models\Media;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageInterface;
use Intervention\Image\Interfaces\ImageManagerInterface;
use Intervention\Image\Typography\FontFactory;
use RuntimeException;

/**
 * 三版檔案策略（SASD §2.4）：原檔（收藏）＋展示版（長邊 ≤2048）＋縮圖（長邊 ≤512，webp）。
 * 浮水印為第四版，僅在分享連結開啟浮水印時產生。
 * Intervention Image v4（Imagick driver）。
 */
class ImageProcessor
{
    private ImageManagerInterface $manager;

    public function __construct()
    {
        $this->manager = ImageManager::usingDriver(ImagickDriver::class);
    }

    /** 從原檔產出展示版與縮圖，回寫尺寸並將狀態設為 active。 */
    public function generateDerivatives(Media $media): void
    {
        $disk = Storage::disk('s3');
        $original = $disk->get($media->storage_key);
        if ($original === null) {
            throw new RuntimeException("原檔不存在：{$media->storage_key}");
        }

        $image = $this->manager->decodeBinary($original)->orient();
        $base = $this->baseKey($media->storage_key);

        $display = clone $image;
        $display->scaleDown(config('pawfit.media.display_max_edge'), config('pawfit.media.display_max_edge'));
        $displayKey = "{$base}_display.webp";
        $disk->put($displayKey, $display->encode(new WebpEncoder(quality: 85))->toString());

        $thumb = clone $image;
        $thumb->scaleDown(config('pawfit.media.thumb_max_edge'), config('pawfit.media.thumb_max_edge'));
        $thumbKey = "{$base}_thumb.webp";
        $disk->put($thumbKey, $thumb->encode(new WebpEncoder(quality: 80))->toString());

        $media->forceFill([
            'display_key' => $displayKey,
            'thumb_key' => $thumbKey,
            'width' => $display->width(),
            'height' => $display->height(),
            'status' => 'active',
            'status_note' => null,
        ])->save();
    }

    /** 以展示版壓上斜向平鋪文字浮水印，另存為第四版。 */
    public function generateWatermark(Media $media, string $label): void
    {
        $disk = Storage::disk('s3');
        $sourceKey = $media->display_key ?? $media->storage_key;
        $source = $disk->get($sourceKey);
        if ($source === null) {
            throw new RuntimeException("展示版不存在：{$sourceKey}");
        }

        $image = $this->manager->decodeBinary($source);
        $this->applyWatermark($image, $label);

        $key = $this->baseKey($media->storage_key).'_wm.webp';
        $disk->put($key, $image->encode(new WebpEncoder(quality: 85))->toString());
        $media->forceFill(['watermarked_key' => $key])->save();
    }

    private function applyWatermark(ImageInterface $image, string $label): void
    {
        $w = $image->width();
        $h = $image->height();
        $size = max(18, (int) round(min($w, $h) / 14));
        $stepX = $size * 9;
        $stepY = $size * 5;
        $font = config('pawfit.media.watermark_font');

        for ($row = 0, $y = -$stepY; $y < $h + $stepY; $y += $stepY, $row++) {
            $offset = ($row % 2) * (int) ($stepX / 2);
            for ($x = -$stepX + $offset; $x < $w + $stepX; $x += $stepX) {
                // Imagick 的 stroke 要求不透明文字色，所以用「深色陰影 + 半透明白字」兩層達到對比
                foreach ([[1, 1, 'rgba(0,0,0,0.22)'], [0, 0, 'rgba(255,255,255,0.34)']] as [$dx, $dy, $color]) {
                    $image->text($label, (int) $x + $dx, (int) $y + $dy, function (FontFactory $f) use ($font, $size, $color) {
                        if (is_file($font)) {
                            $f->filename($font);
                        }
                        $f->size($size);
                        $f->color($color);
                        $f->align('center', 'center');
                        $f->angle(-28);
                    });
                }
            }
        }
    }

    /** 去掉副檔名，衍生版本都掛在同一前綴下。 */
    private function baseKey(string $storageKey): string
    {
        $dot = strrpos($storageKey, '.');

        return $dot === false ? $storageKey : substr($storageKey, 0, $dot);
    }
}
