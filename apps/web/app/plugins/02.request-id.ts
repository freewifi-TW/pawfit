/**
 * 把 SSR 這次請求的 request_id 帶進 Nuxt state（水合到瀏覽器），
 * 讓瀏覽器端錯誤回報（03.client-log）能連回「這一頁是哪個請求渲染的」。
 * 同時在 auth 之後把 user_id 寫回 Nitro event.context，供 http.response log 使用。
 */
export default defineNuxtPlugin(() => {
  const requestId = useState<string | null>('log.requestId', () => null)

  if (import.meta.server) {
    const event = useRequestEvent()
    requestId.value = event?.context.requestId ?? null

    const { me } = useAuth()
    const userId = (me.value as { id?: string } | null)?.id
    if (event && userId) event.context.userId = userId
  }
})
