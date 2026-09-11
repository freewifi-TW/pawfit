/**
 * 登入者的語言偏好（user.locale）優先於 cookie：
 * auth plugin 取得 me 之後，若 me.locale 是支援的語言且與目前不同，就切過去（SSR 首屏就正確）。
 */
export default defineNuxtPlugin(async (nuxtApp) => {
  const { me } = useAuth()
  const i18n = nuxtApp.$i18n
  const preferred = me.value?.locale
  if (!preferred || preferred === i18n.locale.value) return
  if (!i18n.locales.value.some(l => l.code === preferred)) return
  await i18n.setLocale(preferred as typeof i18n.locale.value)
})
