import type { FetchError } from 'ofetch'
import { appendResponseHeader } from 'h3'
import { levelForStatus, logServer, pathOnly } from '#shared/utils/log'
import type { ApiError } from '~/types/api'
import type { ClientLog } from '~/plugins/03.client-log.client'

/**
 * 呼叫 Laravel API 的 $fetch 實例。
 * - 瀏覽器端：same-domain 相對路徑 /api，帶 cookie；寫入類請求自動附 XSRF token。
 *   5xx 或斷線會透過 $clientLog 回報（service=browser, event=client.api_error）。
 * - SSR 端：直接打容器內的 Laravel，轉送瀏覽器 cookie、Referer（Sanctum stateful 判斷用）與
 *   X-Request-Id（讓 Laravel 的 log 跟這次頁面請求串起來），並把 Laravel 回的 Set-Cookie 轉回瀏覽器。
 *   每次呼叫記一筆 http.outbound（docs/logging.md）。
 */
export function useApi() {
  const config = useRuntimeConfig()
  const requestHeaders = useRequestHeaders(['cookie'])
  const event = useRequestEvent()
  const requestId = event?.context.requestId
  const nuxtApp = useNuxtApp()
  const clientLog = import.meta.client ? (nuxtApp.$clientLog as ClientLog | undefined) : undefined
  // 讓 Laravel 用同一種語言回訊息（驗證錯誤、業務錯誤）
  const acceptLanguage = () => nuxtApp.$i18n?.locale.value ?? 'zh-TW'

  const api = $fetch.create({
    baseURL: import.meta.server ? config.apiInternalBase : config.public.apiBase,
    credentials: 'include',
    headers: import.meta.server
      ? {
          cookie: requestHeaders.cookie ?? '',
          referer: `${config.public.siteUrl}/`,
          accept: 'application/json',
          ...(requestId ? { 'x-request-id': requestId } : {})
        }
      : { accept: 'application/json' },
    retry: 0,
    async onRequest({ options }) {
      startedAt.set(options, performance.now())
      const headers = new Headers(options.headers as HeadersInit)
      headers.set('Accept-Language', acceptLanguage())
      options.headers = headers
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
    onResponse({ request, options, response }) {
      if (import.meta.server) {
        if (event) {
          const setCookie = response.headers.getSetCookie?.() ?? []
          for (const c of setCookie) appendResponseHeader(event, 'set-cookie', c)
        }
        logServer(levelForStatus(response.status), 'http.outbound', `outbound ${(options.method ?? 'GET').toUpperCase()} api`, {
          request_id: requestId ?? null,
          user_id: event?.context.userId ?? null,
          context: {
            client: 'api',
            method: (options.method ?? 'GET').toUpperCase(),
            path: apiPath(request),
            status: response.status,
            duration_ms: durationSince(options)
          }
        })
      }
    },
    onRequestError({ request, options, error }) {
      const method = (options.method ?? 'GET').toUpperCase()
      if (import.meta.server) {
        logServer('error', 'http.outbound', `outbound ${method} api failed`, {
          request_id: requestId ?? null,
          context: { client: 'api', method, path: apiPath(request), status: null, duration_ms: durationSince(options) },
          error
        })
      } else {
        clientLog?.('api_error', 'error', `API ${method} ${apiPath(request)} 連線失敗`, {
          method,
          path: apiPath(request),
          status: 0
        }, error)
      }
    },
    onResponseError({ request, options, response }) {
      // 瀏覽器端：4xx 是正常業務流程（未登入、驗證失敗），只回報 5xx
      if (import.meta.client && response.status >= 500) {
        const method = (options.method ?? 'GET').toUpperCase()
        clientLog?.('api_error', 'error', `API ${method} ${apiPath(request)} → ${response.status}`, {
          method,
          path: apiPath(request),
          status: response.status,
          api_request_id: response.headers.get('x-request-id')
        })
      }
    }
  })

  return api
}

/** 只留路徑：去掉 SSR 的 http://api:8080 前綴與 query string */
function apiPath(request: unknown): string {
  return pathOnly(String(request)).replace(/^https?:\/\/[^/]+/, '')
}

/** 每次呼叫的開始時間（key 是 ofetch 的 options 物件，onRequest → onResponse 之間同一個） */
const startedAt = new WeakMap<object, number>()

function durationSince(options: object): number | null {
  const t = startedAt.get(options)
  startedAt.delete(options)
  return typeof t === 'number' ? Math.round(performance.now() - t) : null
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
    const key = status === 401
      ? 'unauthorized'
      : status === 403
        ? 'forbidden'
        : status === 404
          ? 'notFound'
          : status === 429
            ? 'tooMany'
            : status >= 500 ? 'server' : 'generic'
    message = translateApiMessage(key)
  }
  return { status, message, code: data?.code, errors }
}

type ApiMessageKey = 'unauthorized' | 'forbidden' | 'notFound' | 'tooMany' | 'server' | 'generic'

/** 沒有 Nuxt context（setup 之外、測試）時退回的預設文字，正常情況走 common.api.* 語言檔。 */
const API_MESSAGE_FALLBACK: Record<ApiMessageKey, string> = {
  unauthorized: '請先登入。',
  forbidden: '沒有權限執行這個操作。',
  notFound: '找不到資料。',
  tooMany: '操作太頻繁，稍後再試。',
  server: '伺服器發生錯誤，請稍後再試。',
  generic: '發生錯誤，請再試一次。'
}

function translateApiMessage(key: ApiMessageKey): string {
  const i18nKey = `common.api.${key}`
  try {
    const translated = useNuxtApp().$i18n.t(i18nKey)
    return translated && translated !== i18nKey ? translated : API_MESSAGE_FALLBACK[key]
  } catch {
    return API_MESSAGE_FALLBACK[key]
  }
}
