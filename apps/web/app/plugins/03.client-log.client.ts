import { serializeError, type LogLevel } from '#shared/utils/log'

/**
 * 瀏覽器端錯誤回報：Vue 錯誤、未攔截例外、unhandled rejection、API 5xx／斷線，
 * 批次 POST 到 /_log/client（server/routes/_log/client.post.ts），寫成 service=browser 的統一格式。
 *
 * 用法（其他地方）：useNuxtApp().$clientLog('event_name', 'error', 'message', { ...context }, err)
 */

interface ClientLogItem {
  level: LogLevel
  event: string
  message: string
  context: Record<string, unknown>
  exception?: ReturnType<typeof serializeError>
  page_request_id: string | null
  user_id: string | null
  url: string
}

export type ClientLog = (event: string, level: LogLevel, message: string, context?: Record<string, unknown>, error?: unknown) => void

const ENDPOINT = '/_log/client'
const FLUSH_DELAY = 800
const MAX_QUEUE = 20
const DEDUPE_WINDOW = 10_000

export default defineNuxtPlugin((nuxtApp) => {
  const pageRequestId = useState<string | null>('log.requestId').value
  const { me } = useAuth()

  const queue: ClientLogItem[] = []
  const recent = new Map<string, number>()
  let timer: ReturnType<typeof setTimeout> | null = null

  function flush() {
    if (timer) {
      clearTimeout(timer)
      timer = null
    }
    if (!queue.length) return
    const batch = queue.splice(0, 10)
    const body = JSON.stringify(batch)
    const blob = new Blob([body], { type: 'application/json' })
    let sent: boolean
    try {
      sent = navigator.sendBeacon?.(ENDPOINT, blob) ?? false
    } catch {
      sent = false
    }
    if (!sent) {
      fetch(ENDPOINT, { method: 'POST', body, keepalive: true, headers: { 'content-type': 'application/json' } }).catch(() => {})
    }
  }

  const report: ClientLog = (event, level, message, context = {}, error) => {
    const key = `${event}|${message}`
    const now = Date.now()
    const last = recent.get(key)
    if (last && now - last < DEDUPE_WINDOW) return
    recent.set(key, now)
    if (recent.size > 100) recent.clear()

    if (queue.length >= MAX_QUEUE) return
    queue.push({
      level,
      event,
      message: message.slice(0, 2000),
      context: { ...context, route: nuxtApp._route?.fullPath ?? location.pathname },
      exception: error === undefined ? undefined : serializeError(error),
      page_request_id: pageRequestId,
      user_id: (me.value as { id?: string } | null)?.id ?? null,
      url: location.href
    })
    if (!timer) timer = setTimeout(flush, FLUSH_DELAY)
  }

  nuxtApp.hook('vue:error', (err, _instance, info) => {
    report('vue_error', 'error', errorMessage(err), { info }, err)
  })
  nuxtApp.hook('app:error', (err) => {
    report('app_error', 'error', errorMessage(err), {}, err)
  })
  window.addEventListener('error', (e) => {
    report('window_error', 'error', e.message || errorMessage(e.error), {
      source: e.filename || null,
      line: e.lineno || null,
      column: e.colno || null
    }, e.error)
  })
  window.addEventListener('unhandledrejection', (e) => {
    report('unhandled_rejection', 'error', errorMessage(e.reason), {}, e.reason)
  })
  window.addEventListener('pagehide', flush)

  return { provide: { clientLog: report } }
})

function errorMessage(err: unknown): string {
  if (err instanceof Error) return err.message || err.name
  if (typeof err === 'string') return err
  try {
    return JSON.stringify(err) ?? String(err)
  } catch {
    return String(err)
  }
}
