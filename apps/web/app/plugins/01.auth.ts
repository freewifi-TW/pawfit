/** SSR 時先取得登入者，讓 middleware 與頁面首屏就有 me；client 端由 useState 水合。 */
export default defineNuxtPlugin(async () => {
  // 嵌入卡片（/embed/*）沒有登入狀態、不設 cookie（FR-7.4）
  if (useRequestURL().pathname.startsWith('/embed/')) return

  const { fetchMe, loaded } = useAuth()
  if (import.meta.server || !loaded.value) {
    await fetchMe()
  }
})
