<?php

namespace App\Services;

use App\Models\Fursona;

/**
 * SVG 色票卡（FR-7.3）：純靜態 SVG，無 script、無外部資源，可直接當 <img> 或貼進 Markdown。
 * 版面：每格＝色塊 + 名稱 + hex + 部位備註；row＝單列（最多 12 格，超過自動改 grid），grid＝每列 6 格。
 */
class PaletteSvg
{
    public const THEMES = ['light', 'dark'];

    public const LAYOUTS = ['row', 'grid'];

    private const CELL_W = 104;

    private const CELL_H = 132;

    private const SWATCH = 72;

    private const PAD = 20;

    private const HEADER = 44;

    private const FOOTER = 26;

    private const GRID_COLS = 6;

    private const ROW_MAX = 12;

    public function render(Fursona $fursona, string $theme = 'light', string $layout = 'row'): string
    {
        $c = $this->colors($theme);
        $palette = array_values($fursona->palette ?? []);
        $n = count($palette);

        $cols = ($layout === 'row' && $n <= self::ROW_MAX) ? max(1, $n) : self::GRID_COLS;
        $cols = min($cols, max(1, $n));
        $rows = $n === 0 ? 1 : (int) ceil($n / $cols);

        $width = self::PAD * 2 + max(3, $cols) * self::CELL_W;
        $height = self::PAD + self::HEADER + $rows * self::CELL_H + self::FOOTER;

        $title = $this->esc($this->clip($fursona->name, 28));
        $species = $fursona->species ? $this->esc($this->clip($fursona->species, 20)) : '';

        $out = [];
        $out[] = '<svg xmlns="http://www.w3.org/2000/svg" width="'.$width.'" height="'.$height.'" viewBox="0 0 '.$width.' '.$height.'" role="img" aria-label="'.$title.' palette">';
        $out[] = '<title>'.$title.' · Pawfit palette</title>';
        $out[] = '<rect width="'.$width.'" height="'.$height.'" rx="20" fill="'.$c['bg'].'" stroke="'.$c['line'].'" stroke-width="2"/>';
        $out[] = '<text x="'.self::PAD.'" y="'.(self::PAD + 22).'" font-family="\'Baloo 2\',\'Noto Sans TC\',system-ui,sans-serif" font-weight="800" font-size="20" fill="'.$c['ink'].'">'.$title.'</text>';
        if ($species !== '') {
            $out[] = '<text x="'.($width - self::PAD).'" y="'.(self::PAD + 22).'" text-anchor="end" font-family="\'Noto Sans TC\',system-ui,sans-serif" font-size="12" fill="'.$c['ink2'].'">'.$species.'</text>';
        }

        $top = self::PAD + self::HEADER;
        if ($n === 0) {
            $out[] = '<text x="'.($width / 2).'" y="'.($top + 40).'" text-anchor="middle" font-family="\'Noto Sans TC\',system-ui,sans-serif" font-size="13" fill="'.$c['ink3'].'">no palette yet</text>';
        }
        foreach ($palette as $i => $p) {
            $col = $i % $cols;
            $row = intdiv($i, $cols);
            $x = self::PAD + $col * self::CELL_W;
            $y = $top + $row * self::CELL_H;
            $hex = $this->hex($p['hex'] ?? '');
            $name = $this->esc($this->clip((string) ($p['name'] ?? ''), 10));
            $note = $this->esc($this->clip((string) ($p['note'] ?? ''), 12));

            $out[] = '<rect x="'.($x + 8).'" y="'.$y.'" width="'.self::SWATCH.'" height="'.self::SWATCH.'" rx="16" fill="'.$hex.'" stroke="'.$c['line'].'" stroke-width="2"/>';
            if ($name !== '') {
                $out[] = '<text x="'.($x + 8 + self::SWATCH / 2).'" y="'.($y + self::SWATCH + 18).'" text-anchor="middle" font-family="\'Noto Sans TC\',system-ui,sans-serif" font-weight="700" font-size="12" fill="'.$c['ink'].'">'.$name.'</text>';
            }
            $out[] = '<text x="'.($x + 8 + self::SWATCH / 2).'" y="'.($y + self::SWATCH + 34).'" text-anchor="middle" font-family="\'Fira Code\',ui-monospace,monospace" font-size="11" fill="'.$c['ink2'].'">'.$hex.'</text>';
            if ($note !== '') {
                $out[] = '<text x="'.($x + 8 + self::SWATCH / 2).'" y="'.($y + self::SWATCH + 49).'" text-anchor="middle" font-family="\'Noto Sans TC\',system-ui,sans-serif" font-size="10" fill="'.$c['ink3'].'">'.$note.'</text>';
            }
        }

        $out[] = '<text x="'.($width - self::PAD).'" y="'.($height - 10).'" text-anchor="end" font-family="\'Baloo 2\',system-ui,sans-serif" font-weight="800" font-size="11" fill="'.$c['accent'].'">pawfit</text>';
        $out[] = '</svg>';

        return implode("\n", $out);
    }

    private function colors(string $theme): array
    {
        return $theme === 'dark'
            ? ['bg' => '#1d1930', 'line' => '#3a3356', 'ink' => '#f1eefb', 'ink2' => '#b8b2d0', 'ink3' => '#8f88ad', 'accent' => '#ff8f6e']
            : ['bg' => '#fbfaff', 'line' => '#d8d0ea', 'ink' => '#2a2352', 'ink2' => '#5b5480', 'ink3' => '#8f88ad', 'accent' => '#ff7a59'];
    }

    /** 只接受 #RRGGBB，其他一律灰色，避免任何注入。 */
    private function hex(string $hex): string
    {
        return preg_match('/^#[0-9A-Fa-f]{6}$/', $hex) ? strtoupper($hex) : '#CCCCCC';
    }

    private function clip(string $s, int $max): string
    {
        $s = trim($s);

        return mb_strlen($s) > $max ? mb_substr($s, 0, $max - 1).'…' : $s;
    }

    private function esc(string $s): string
    {
        return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
