import type { ReportReason, Visibility } from '~/types/api'

/**
 * 不需要翻譯的對照與工具。
 * 顯示文字（可見度、圖片類型、檢舉原因、管理動作、日期格式）改在 composables/useLabels.ts，依語言切換。
 */

export const VISIBILITY_PILL: Record<Visibility, string> = {
  public: 'ok',
  unlisted: 'warn',
  private: 'danger'
}

export const REASON_PILL: Record<ReportReason, string> = {
  illegal: 'danger',
  untagged_nsfw: 'warn',
  copyright: '',
  harassment: '',
  other: ''
}

export function formatBytes(n: number | null | undefined): string {
  if (!n) return '0 B'
  const units = ['B', 'KB', 'MB', 'GB']
  let i = 0
  let v = n
  while (v >= 1024 && i < units.length - 1) {
    v /= 1024
    i++
  }
  return `${v.toFixed(i >= 2 ? 2 : 0)} ${units[i]}`
}

/** 貼紙式標籤的顏色與傾斜，依索引輪替。 */
export function tagStyle(i: number): { class: string, style: string } {
  const colors = ['mint', '', 'butter', 'sky', '']
  const tilts = [-2, 1.5, -1, 2, -1.5]
  return {
    class: colors[i % colors.length] ?? '',
    style: `--r:${tilts[i % tilts.length]}deg`
  }
}

/** 沒有圖時用色票做封面漸層。 */
export function paletteGradient(palette: { hex: string }[] | undefined): string {
  const hexes = (palette ?? []).slice(0, 3).map(p => p.hex)
  if (hexes.length === 0) return 'linear-gradient(160deg,#D9642A,#F4E7D3 55%,#2B2420)'
  if (hexes.length === 1) return `linear-gradient(160deg,${hexes[0]},#F4E7D3)`
  if (hexes.length === 2) return `linear-gradient(160deg,${hexes[0]},${hexes[1]})`
  return `linear-gradient(160deg,${hexes[0]},${hexes[1]} 55%,${hexes[2]})`
}
