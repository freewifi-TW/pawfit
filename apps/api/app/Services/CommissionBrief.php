<?php

namespace App\Services;

use App\Models\CommissionKit;

/**
 * 委託需求單描述文字（FR-6.2）：由快照資料以模板組合，輸出所有支援語言。
 * 純文字、Discord／Twitter DM 友善；色票 hex 與繪師 credit 直接取自原始資料，不經任何改寫。
 */
class CommissionBrief
{
    /** @return array<string, string> locale（BCP 47）→ 文字 */
    public function renderAll(CommissionKit $kit): array
    {
        $out = [];
        foreach (config('pawfit.locales.supported') as $locale) {
            $out[$locale] = $this->render($kit, $locale);
        }

        return $out;
    }

    public function render(CommissionKit $kit, string $locale): string
    {
        $l = str_replace('-', '_', $locale); // zh-TW → lang/zh_TW
        $t = fn (string $key, array $replace = []) => trans("commission.{$key}", $replace, $l);
        $s = $kit->snapshot;
        $f = $s['fursona'] ?? [];
        $lines = [];

        $lines[] = filled($f['species'] ?? null)
            ? $t('title_species', ['name' => $f['name'] ?? '', 'species' => $f['species']])
            : $t('title', ['name' => $f['name'] ?? '']);
        $lines[] = $t('generated_by', ['pawfit_id' => $s['owner']['pawfit_id'] ?? 'pawfit', 'date' => $kit->created_at?->format('Y-m-d') ?? now()->format('Y-m-d')]);
        $lines[] = $t('kit_link', ['url' => $kit->url()]);
        if (filled($s['share_url'] ?? null)) {
            $lines[] = $t('share_link', ['url' => $s['share_url']]);
        }
        $lines[] = '';

        // 角色
        $lines[] = $t('section_character');
        $lines[] = $t('name', ['name' => $f['name'] ?? '']);
        if (filled($f['species'] ?? null)) {
            $lines[] = $t('species', ['species' => $f['species']]);
        }
        if (! empty($f['tags'])) {
            $lines[] = $t('tags', ['tags' => implode($l === 'zh_TW' ? '、' : ', ', $f['tags'])]);
        }
        if (filled($f['bio'] ?? null)) {
            $lines[] = $t('bio');
            foreach (preg_split('/\r?\n/', trim($f['bio'])) as $p) {
                $lines[] = '  '.$p;
            }
        }
        $lines[] = '';

        // 色票（原始資料）
        $lines[] = $t('section_palette');
        $palette = array_values($f['palette'] ?? []);
        if ($palette === []) {
            $lines[] = $t('palette_empty');
        }
        foreach ($palette as $i => $p) {
            $name = filled($p['name'] ?? null) ? $p['name'] : $t('palette_unnamed', ['index' => $i + 1]);
            $params = ['index' => $i + 1, 'name' => $name, 'hex' => $p['hex'] ?? '', 'note' => $p['note'] ?? ''];
            $lines[] = filled($p['note'] ?? null) ? $t('palette_item_note', $params) : $t('palette_item', $params);
        }
        $lines[] = '';

        // 參考圖（credit 原始資料）
        $media = array_values($s['media'] ?? []);
        $lines[] = $t('section_references', ['count' => count($media)]);
        foreach ($media as $i => $m) {
            $caption = filled($m['caption'] ?? null) ? $m['caption'] : $t('reference_untitled', ['index' => $i + 1]);
            if (! empty($m['is_nsfw'])) {
                $caption .= ' '.$t('reference_nsfw');
            }
            $params = ['index' => $i + 1, 'caption' => $caption, 'credit' => $m['credit_name'] ?? ''];
            $line = filled($m['credit_name'] ?? null) ? $t('reference_item_credit', $params) : $t('reference_item', $params);
            if (filled($m['credit_url'] ?? null)) {
                $line .= ' '.$m['credit_url'];
            }
            $lines[] = $line;
        }
        $lines[] = '';

        // 本次委託
        $lines[] = $t('section_request');
        $labels = trans('commission.request_fields', [], $l);
        $any = false;
        foreach (CommissionKit::REQUEST_FIELDS as $field) {
            $value = $s['request'][$field] ?? null;
            if (! filled($value)) {
                continue;
            }
            $any = true;
            $value = trim((string) $value);
            if (str_contains($value, "\n")) {
                $lines[] = ($labels[$field] ?? $field).'：';
                foreach (preg_split('/\r?\n/', $value) as $p) {
                    $lines[] = '  '.$p;
                }
            } else {
                $lines[] = ($labels[$field] ?? $field).($l === 'zh_TW' ? '：' : ': ').$value;
            }
        }
        if (! $any) {
            $lines[] = $t('request_empty');
        }
        $lines[] = '';
        $lines[] = $t('footer');

        return implode("\n", $lines);
    }
}
