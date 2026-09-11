import type { MediaKind, ReportReason, Visibility } from '~/types/api'

export const VISIBILITY_LABEL: Record<Visibility, string> = {
  public: '公開',
  unlisted: '連結可見',
  private: '私人'
}

export const VISIBILITY_PILL: Record<Visibility, string> = {
  public: 'ok',
  unlisted: 'warn',
  private: 'danger'
}

export const KIND_LABEL: Record<MediaKind, string> = {
  art2d: '2D',
  model3d: '3D',
  photo: '實體'
}

export const KIND_LABEL_LONG: Record<MediaKind, string> = {
  art2d: '2D 平面作品',
  model3d: '3D 模型',
  photo: '實體照片'
}

export const CREDIT_PREFIX: Record<MediaKind, string> = {
  art2d: '繪師',
  model3d: '製作',
  photo: '工作室'
}

export const REASON_LABEL: Record<ReportReason, string> = {
  illegal: '非法內容',
  untagged_nsfw: '未標記 NSFW',
  copyright: '侵權 / 盜圖',
  harassment: '騷擾',
  other: '其他'
}

export const REASON_PILL: Record<ReportReason, string> = {
  illegal: 'danger',
  untagged_nsfw: 'warn',
  copyright: '',
  harassment: '',
  other: ''
}

export const ADMIN_ACTION_LABEL: Record<string, string> = {
  remove_media: '下架圖片',
  purge_media: '緊急刪除圖片',
  restore_media: '還原圖片',
  mark_nsfw: '改標 NSFW',
  remove_fursona: '下架獸設',
  restore_fursona: '還原獸設',
  ban_user: '停權帳號',
  unban_user: '解除停權',
  resolve_report: '結案',
  dismiss_report: '駁回'
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

export function formatDate(iso: string | null | undefined, withTime = false): string {
  if (!iso) return ''
  const d = new Date(iso)
  const date = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
  if (!withTime) return date
  return `${date} ${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`
}

export function formatMonth(iso: string | null | undefined): string {
  if (!iso) return ''
  const d = new Date(iso)
  return `${d.getFullYear()} 年 ${d.getMonth() + 1} 月`
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
