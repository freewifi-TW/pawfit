import type { FetchError } from 'ofetch'
import { appendResponseHeader } from 'h3'
import type { ApiError } from '~/types/api'

/**
 * 呼叫 Laravel API 的 $fetch 實例。
 * - 瀏覽器端：same-domain 相對路徑 /api，帶 cookie；寫入類請求自動附 XSRF token。
 * - SSR 端：直接打容器內的 Laravel，轉送瀏覽器 cookie 與 Referer（Sanctum stateful 判斷用），
 *   並把 Laravel 回的 Set-Cookie 轉回瀏覽器。
 */
export function useApi() {
  const config = useRuntimeConfig()
  const requestHeaders = useRequestHeaders(['cookie'])
  const event = useRequestEvent()

  const api = $fetch.create({
    baseURL: import.meta.server ? config.apiInternalBase : config.public.apiBase,
    credentials: 'include',
    headers: import.meta.server
      ? { cookie: requestHeaders.cookie ?? '', referer: `${config.public.siteUrl}/`, accept: 'application/json' }
      : { accept: 'application/json' },
    retry: 0,
    async onRequest({ options }) {
      if (import.meta.client) {
        const method = (options.method ?? 'GET').toUpperCase()
        if (method !== 'GET' && method !== 'HEAD') {
          const token = await ensureCsrfToken()
          if (token) {
            const headers = new Headers(options.headers as HeadersInit)
            headers.set('X-XSRF-TOKEN', token)
            options.headers = headers
          }
        }
      }
    },
    onResponse({ response }) {
      if (import.meta.server && event) {
        const setCookie = response.headers.getSetCookie?.() ?? []
        for (const c of setCookie) appendResponseHeader(event, 'set-cookie', c)
      }
    },
    onResponseError({ request, response }) {
      // SSR 端的 API 失敗只會變成頁面 404/500，這裡留一行 log 方便追查
      if (import.meta.server && response.status !== 401) {
        console.warn(`[api] ${String(request)} → ${response.status}`, response._data?.message ?? '')
      }
    }
  })

  return api
}

function readCookie(name: string): string | null {
  if (import.meta.server) return null
  const m = document.cookie.match(new RegExp('(?:^|; )' + name.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '=([^;]*)'))
  return m ? decodeURIComponent(m[1]!) : null
}

async function ensureCsrfToken(): Promise<string | null> {
  let token = readCookie('XSRF-TOKEN')
  if (!token) {
    await $fetch('/sanctum/csrf-cookie', { credentials: 'include' })
    token = readCookie('XSRF-TOKEN')
  }
  return token
}

/** 把 ofetch 的錯誤整理成可顯示的訊息與欄位錯誤。 */
export function apiError(e: unknown): { status: number, message: string, code?: string, errors: Record<string, string> } {
  const err = e as FetchError<ApiError>
  const status = err?.response?.status ?? 0
  const data = err?.data
  const errors: Record<string, string> = {}
  if (data?.errors) {
    for (const [k, v] of Object.entries(data.errors)) errors[k] = v[0] ?? ''
  }
  let message = data?.message || ''
  if (!message) {
    message = status === 401
      ? '請先登入。'
      : status === 403
        ? '沒有權限執行這個操作。'
        : status === 404
          ? '找不到資料。'
          : status === 429
            ? '操作太頻繁，稍後再試。'
            : status >= 500 ? '伺服器發生錯誤，請稍後再試。' : '發生錯誤，請再試一次。'
  }
  return { status, message, code: data?.code, errors }
}
