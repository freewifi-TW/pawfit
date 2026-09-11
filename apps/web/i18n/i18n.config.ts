/**
 * vue-i18n 選項（@nuxtjs/i18n 的 vueI18n）。
 * 語言清單在 nuxt.config.ts 的 SUPPORTED_LOCALES；這裡放日期／數字格式。
 * 新增語言時在 datetimeFormats / numberFormats 各補一組。
 */
const datetime = {
  date: { year: 'numeric', month: '2-digit', day: '2-digit' },
  datetime: { year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', hour12: false },
  month: { year: 'numeric', month: 'long' }
} as const

export default defineI18nConfig(() => ({
  legacy: false,
  fallbackLocale: 'zh-TW',
  fallbackWarn: false,
  missingWarn: false,
  datetimeFormats: {
    'zh-TW': datetime,
    'en': datetime
  },
  numberFormats: {
    'zh-TW': { integer: { maximumFractionDigits: 0 } },
    'en': { integer: { maximumFractionDigits: 0 } }
  }
}))
