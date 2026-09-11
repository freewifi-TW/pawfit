import type { Me } from '~/types/api'

/**
 * 語言切換：
 * - 訪客：只改 i18n（cookie pawfit_locale 由 @nuxtjs/i18n 維護）
 * - 登入者：同時 PATCH /me { locale }，下次任何裝置登入都沿用（見 plugins/04.locale-sync）
 * 語言清單來自 nuxt.config 的 SUPPORTED_LOCALES（透過 i18n 的 locales）。
 */
export function useLocale() {
  const { locale, locales, setLocale } = useI18n()
  const { me } = useAuth()
  const api = useApi()

  const available = computed(() => locales.value.map(l => ({ code: l.code, name: l.name ?? l.code })))

  async function switchLocale(code: string) {
    if (code === locale.value) return
    if (!available.value.some(l => l.code === code)) return
    await setLocale(code as typeof locale.value)

    if (me.value && me.value.locale !== code) {
      try {
        me.value = await api<Me>('/me', { method: 'PATCH', body: { locale: code } })
      } catch {
        // 存不進後端不影響這次切換，下次登入會再同步
      }
    }
  }

  return { locale, available, switchLocale }
}
