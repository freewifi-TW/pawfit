import type { MediaKind, NsfwPref, ReportReason, Visibility } from '~/types/api'

/**
 * 列舉值的顯示文字與日期格式（依目前語言）。文字在 i18n/locales/<locale>/enums.json。
 * 顏色 / 樣式對照（VISIBILITY_PILL、REASON_PILL）與不需翻譯的工具仍在 utils/labels.ts。
 */
export function useLabels() {
  const { t, d, te } = useI18n()

  const enumLabel = (group: string, value: string) => {
    const key = `enums.${group}.${value}`
    return te(key) ? t(key) : value
  }

  return {
    visibilityLabel: (v: Visibility) => enumLabel('visibility', v),
    kindLabel: (k: MediaKind) => enumLabel('kind', k),
    kindLabelLong: (k: MediaKind) => enumLabel('kindLong', k),
    creditPrefix: (k: MediaKind) => enumLabel('creditPrefix', k),
    reasonLabel: (r: ReportReason) => enumLabel('reason', r),
    adminActionLabel: (a: string) => enumLabel('adminAction', a),
    nsfwPrefLabel: (p: NsfwPref) => enumLabel('nsfwPref', p),

    /** 2026-09-11 或 2026-09-11 13:05（依語言的數字日期格式） */
    formatDate: (iso: string | null | undefined, withTime = false) =>
      iso ? d(new Date(iso), withTime ? 'datetime' : 'date') : '',
    /** 2026 年 9 月 / September 2026 */
    formatMonth: (iso: string | null | undefined) => (iso ? d(new Date(iso), 'month') : '')
  }
}
