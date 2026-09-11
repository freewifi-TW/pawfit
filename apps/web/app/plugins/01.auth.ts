/** SSR 時先取得登入者，讓 middleware 與頁面首屏就有 me；client 端由 useState 水合。 */
export default defineNuxtPlugin(async () => {
  const { fetchMe, loaded } = useAuth()
  if (import.meta.server || !loaded.value) {
    await fetchMe()
  }
})
