<?php

namespace App\Services;

use App\Models\CommissionKit;
use App\Models\Media;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageInterface;
use Intervention\Image\Interfaces\ImageManagerInterface;
use Intervention\Image\Typography\FontFactory;

/**
 * 委託需求單合成圖（FR-6.3）：參考圖縮圖＋色票色卡排成一張 webp，給繪師一眼看完、也當 OG 預覽。
 * 純排版合成，不做任何生成或重繪。字型走 config('pawfit.commission.font')（fontconfig 名稱或檔案路徑）。
 */
class CommissionSheet
{
    private const W = 2048;

    private const PAD = 72;

    private const GAP = 40;

    private const COLS = 3;

    private const SWATCH = 150;

    private const SWATCH_COLS = 9;

    private ImageManagerInterface $manager;

    public function __construct()
    {
        $this->manager = ImageManager::usingDriver(ImagickDriver::class);
    }

    /** 回傳 webp 二進位。 */
    public function render(CommissionKit $kit): string
    {
        $s = $kit->snapshot;
        $f = $s['fursona'] ?? [];
        $palette = array_values($f['palette'] ?? []);
        $media = $kit->media();
        $thumbs = $this->loadThumbs($media);

        $cellW = (int) floor((self::W - self::PAD * 2 - self::GAP * (self::COLS - 1)) / self::COLS);
        $cellH = $cellW + 110; // 圖 + caption + credit
        $rows = $thumbs->isEmpty() ? 0 : (int) ceil($thumbs->count() / self::COLS);
        $swatchRows = $palette === [] ? 0 : (int) ceil(count($palette) / self::SWATCH_COLS);

        $headerH = 200;
        $paletteH = $swatchRows === 0 ? 0 : 70 + $swatchRows * (self::SWATCH + 120);
        $refsH = $rows === 0 ? 0 : 70 + $rows * ($cellH + self::GAP);
        $footerH = 90;
        $h = self::PAD + $headerH + $paletteH + $refsH + $footerH;

        $img = $this->manager->createImage(self::W, $h)->fill('#fbfaff');
        $this->rect($img, 0, 0, self::W, $h, 'transparent', '#d8d0ea', 8);

        // ---- 標題
        $y = self::PAD;
        $title = $this->clip((string) ($f['name'] ?? ''), 24);
        $this->text($img, $title, self::PAD, $y + 70, 72, '#2a2352', 'left');
        $sub = array_filter([
            $f['species'] ?? null,
            '@'.($s['owner']['pawfit_id'] ?? 'pawfit'),
            $kit->created_at?->format('Y-m-d'),
        ]);
        $this->text($img, implode('  ·  ', $sub), self::PAD, $y + 130, 32, '#5b5480', 'left');
        $this->text($img, 'pawfit', self::W - self::PAD, $y + 70, 56, '#ff7a59', 'right');
        $this->text($img, $this->clip($kit->url(), 60), self::W - self::PAD, $y + 130, 26, '#8f88ad', 'right');
        $y += $headerH;

        // ---- 色票
        if ($swatchRows > 0) {
            $this->text($img, $this->label('palette'), self::PAD, $y + 40, 36, '#2a2352', 'left');
            $y += 70;
            $cellWidth = (int) floor((self::W - self::PAD * 2) / self::SWATCH_COLS);
            foreach ($palette as $i => $p) {
                $col = $i % self::SWATCH_COLS;
                $row = intdiv($i, self::SWATCH_COLS);
                $x = self::PAD + $col * $cellWidth;
                $sy = $y + $row * (self::SWATCH + 120);
                $hex = preg_match('/^#[0-9A-Fa-f]{6}$/', $p['hex'] ?? '') ? strtoupper($p['hex']) : '#CCCCCC';
                $this->rect($img, $x, $sy, self::SWATCH, self::SWATCH, $hex, '#d8d0ea', 4, 28);
                $cx = $x + (int) (self::SWATCH / 2);
                if (filled($p['name'] ?? null)) {
                    $this->text($img, $this->clip($p['name'], 10), $cx, $sy + self::SWATCH + 34, 26, '#2a2352', 'center');
                }
                $this->text($img, $hex, $cx, $sy + self::SWATCH + 64, 24, '#5b5480', 'center');
                if (filled($p['note'] ?? null)) {
                    $this->text($img, $this->clip($p['note'], 12), $cx, $sy + self::SWATCH + 88, 20, '#8f88ad', 'center');
                }
            }
            $y += $swatchRows * (self::SWATCH + 120);
        }

        // ---- 參考圖
        if ($rows > 0) {
            $this->text($img, $this->label('references', $thumbs->count()), self::PAD, $y + 40, 36, '#2a2352', 'left');
            $y += 70;
            foreach ($thumbs->values() as $i => [$m, $thumb]) {
                $col = $i % self::COLS;
                $row = intdiv($i, self::COLS);
                $x = self::PAD + $col * ($cellW + self::GAP);
                $cy = $y + $row * ($cellH + self::GAP);
                $this->rect($img, $x, $cy, $cellW, $cellW, '#f3f0fb', '#d8d0ea', 4, 24);
                /** @var ImageInterface $thumb */
                $thumb->scaleDown($cellW - 16, $cellW - 16);
                $img->insert($thumb, $x + (int) (($cellW - $thumb->width()) / 2), $cy + (int) (($cellW - $thumb->height()) / 2));
                $caption = filled($m->caption) ? $m->caption : '#'.($i + 1);
                $this->text($img, $this->clip($caption, 34), $x + 8, $cy + $cellW + 42, 28, '#2a2352', 'left');
                if (filled($m->credit_name)) {
                    $this->text($img, '© '.$this->clip($m->credit_name, 40), $x + 8, $cy + $cellW + 84, 24, '#5b5480', 'left');
                }
            }
            $y += $rows * ($cellH + self::GAP);
        }

        // ---- 頁尾
        $this->text($img, $this->label('footer'), self::PAD, $h - 36, 22, '#8f88ad', 'left');

        return $img->encode(new WebpEncoder(quality: 85))->toString();
    }

    /** @return Collection<int, array{0: Media, 1: ImageInterface}> */
    private function loadThumbs($media)
    {
        $disk = Storage::disk('s3');

        return $media->map(function (Media $m) use ($disk) {
            $key = $m->display_key ?? $m->thumb_key ?? $m->storage_key;
            $bin = $key ? $disk->get($key) : null;
            if ($bin === null) {
                return null;
            }
            try {
                return [$m, $this->manager->decodeBinary($bin)];
            } catch (\Throwable) {
                return null;
            }
        })->filter()->values();
    }

    private function label(string $key, int $count = 0): string
    {
        return match ($key) {
            'palette' => 'Palette 色票',
            'references' => "References 參考圖 ({$count})",
            default => 'Pawfit 爪搭 · commission brief · no AI generation',
        };
    }

    private function text(ImageInterface $img, string $text, int $x, int $y, int $size, string $color, string $align): void
    {
        if ($text === '') {
            return;
        }
        $font = (string) config('pawfit.commission.font');
        $fallback = (string) config('pawfit.media.watermark_font');
        $img->text($text, $x, $y, function (FontFactory $f) use ($font, $fallback, $size, $color, $align) {
            // Intervention 要求實際檔案；CJK 字型不存在時退回 Baloo2（中文會缺字，但不會失敗）
            $f->filename(is_file($font) ? $font : $fallback);
            $f->size($size);
            $f->color($color);
            $f->align($align, 'bottom');
        });
    }

    private function rect(ImageInterface $img, int $x, int $y, int $w, int $h, string $fill, string $border, int $borderW, int $radius = 0): void
    {
        $img->drawRectangle(function ($r) use ($x, $y, $w, $h, $fill, $border, $borderW) {
            $r->at($x, $y);
            $r->size($w, $h);
            if ($fill !== 'transparent') {
                $r->background($fill);
            }
            $r->border($border, $borderW);
        });
        unset($radius); // Intervention v4 的矩形沒有圓角；保留參數以便日後換 driver
    }

    private function clip(string $s, int $max): string
    {
        $s = trim(preg_replace('/\s+/u', ' ', $s) ?? $s);

        return mb_strlen($s) > $max ? mb_substr($s, 0, $max - 1).'…' : $s;
    }
}
