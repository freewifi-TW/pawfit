import { levelForStatus, logServer, pathOnly } from '#shared/utils/log'

/**
 * Nitro（SSR）層的統一 log：
 *   http.request / http.response — 每個進到 Nuxt server 的請求
 *   exception                    — SSR 未攔截的錯誤（4xx 的 createError 記 http.error）
 *
 * request_id 沿用 Caddy 的 X-Request-Id，沒有就自己產生；放進 event.context 供 useApi 轉送給 Laravel。
 * user_id 由 app/plugins/02.request-id.ts 在 auth 之後寫進 event.context.userId。
 */

const REQUEST_ID = /^[A-Za-z0-9_.:-]{8,128}$/

// 靜態資源、Vite/HMR、devtools 與 log 收集端點本身不記
const SKIP = /^\/(?:_nuxt\/|__nuxt|@vite|@id\/|@fs\/|node_modules\/|_fonts\/|__nuxt_devtools__|_log\/|favicon\.ico|sw\.js|robots\.txt)/

export default defineNitroPlugin((nitro) => {
  nitro.hooks.hook('request', (event) => {
    const incoming = getRequestHeader(event, 'x-request-id') ?? ''
    const requestId = REQUEST_ID.test(incoming) ? incoming : crypto.randomUUID()
    event.context.requestId = requestId
    event.context.startedAt = performance.now()
    setResponseHeader(event, 'x-request-id', requestId)

    if (SKIP.test(event.path)) return

    logServer('info', 'http.request', 'http request', {
      request_id: requestId,
      context: {
        method: event.method,
        path: pathOnly(event.path),
        ip: getRequestIP(event, { xForwardedFor: true }) ?? null,
        user_agent: (getRequestHeader(event, 'user-agent') ?? '').slice(0, 200),
        referer: getRequestHeader(event, 'referer') ?? null
      }
    })
  })

  nitro.hooks.hook('afterResponse', (event) => {
    if (SKIP.test(event.path)) return
    const status = getResponseStatus(event)
    const started = event.context.startedAt
    logServer(levelForStatus(status), 'http.response', 'http response', {
      request_id: event.context.requestId ?? null,
      user_id: event.context.userId ?? null,
      context: {
        method: event.method,
        path: pathOnly(event.path),
        status,
        duration_ms: started === undefined ? null : Math.round(performance.now() - started)
      }
    })
  })

  nitro.hooks.hook('error', (error, { event }) => {
    const statusCode = (error as { statusCode?: number }).statusCode ?? 500
    const isHttpError = statusCode < 500
    logServer(isHttpError ? 'warning' : 'error', isHttpError ? 'http.error' : 'exception', error.message || 'unhandled error', {
      request_id: event?.context.requestId ?? null,
      user_id: event?.context.userId ?? null,
      context: {
        method: event?.method ?? null,
        path: event ? pathOnly(event.path) : null,
        status: statusCode
      },
      error
    })
  })
})
