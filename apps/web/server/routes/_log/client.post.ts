import { LOG_LEVELS, writeLine, type LogLevel, type LogLine, type SerializedError } from '#shared/utils/log'

/**
 * 瀏覽器端錯誤收集端點（app/plugins/03.client-log.client.ts 送過來）。
 * 驗證、限流後以 service=browser 寫成統一格式，跟其他服務一起進 Loki。
 *
 * 不信任瀏覽器送來的內容：只接受白名單欄位、限制長度與筆數、每個 IP 每分鐘最多 60 筆。
 */

const MAX_BODY = 16_000
const MAX_ITEMS = 10
const MAX_STR = 2_000
const MAX_STACK = 15
const RATE_PER_MIN = 60
const EVENT_RE = /^[a-z0-9_.]{1,64}$/
const REQUEST_ID_RE = /^[A-Za-z0-9_.:-]{8,128}$/

type Bucket = { count: number, reset: number }
const buckets = new Map<string, Bucket>()

interface ClientPayload {
  level?: unknown
  event?: unknown
  message?: unknown
  context?: unknown
  exception?: unknown
  page_request_id?: unknown
  user_id?: unknown
  url?: unknown
}

export default defineEventHandler(async (event) => {
  const ip = getRequestIP(event, { xForwardedFor: true }) ?? 'unknown'
  if (!allow(ip)) {
    setResponseStatus(event, 429)
    return null
  }

  const raw = await readRawBody(event, 'utf8')
  if (!raw || raw.length > MAX_BODY) {
    setResponseStatus(event, raw ? 413 : 400)
    return null
  }

  let payload: unknown
  try {
    payload = JSON.parse(raw)
  } catch {
    setResponseStatus(event, 400)
    return null
  }

  const items = (Array.isArray(payload) ? payload : [payload]).slice(0, MAX_ITEMS) as ClientPayload[]
  const collectorRequestId = event.context.requestId ?? null

  for (const item of items) {
    if (typeof item !== 'object' || item === null) continue
    const line = normalize(item, ip, collectorRequestId)
    if (line) writeLine(line)
  }

  setResponseStatus(event, 204)
  return null
})

function normalize(item: ClientPayload, ip: string, collectorRequestId: string | null): LogLine | null {
  const level: LogLevel = LOG_LEVELS.includes(item.level as LogLevel) ? item.level as LogLevel : 'error'
  const rawEvent = typeof item.event === 'string' ? item.event : 'error'
  const eventName = EVENT_RE.test(rawEvent) ? rawEvent : 'error'
  const message = typeof item.message === 'string' ? item.message.slice(0, MAX_STR) : ''
  if (!message) return null

  const pageRequestId = typeof item.page_request_id === 'string' && REQUEST_ID_RE.test(item.page_request_id)
    ? item.page_request_id
    : null

  const context: Record<string, unknown> = {
    ip,
    url: typeof item.url === 'string' ? item.url.slice(0, MAX_STR) : null,
    collector_request_id: collectorRequestId
  }
  if (typeof item.context === 'object' && item.context !== null && !Array.isArray(item.context)) {
    for (const [k, v] of Object.entries(item.context as Record<string, unknown>).slice(0, 20)) {
      if (!/^[a-z0-9_]{1,40}$/i.test(k)) continue
      context[k] = clampValue(v)
    }
  }

  const line: LogLine = {
    ts: new Date().toISOString(),
    level,
    service: 'browser',
    event: `client.${eventName}`,
    message,
    request_id: pageRequestId,
    user_id: typeof item.user_id === 'string' ? item.user_id.slice(0, 64) : null,
    context
  }

  const ex = item.exception
  if (typeof ex === 'object' && ex !== null) {
    const e = ex as Record<string, unknown>
    const serialized: SerializedError = {
      class: typeof e.class === 'string' ? e.class.slice(0, 120) : 'Error',
      message: typeof e.message === 'string' ? e.message.slice(0, MAX_STR) : ''
    }
    if (Array.isArray(e.stack)) {
      serialized.stack = e.stack.slice(0, MAX_STACK).filter((s): s is string => typeof s === 'string').map(s => s.slice(0, 500))
    }
    line.exception = serialized
  }

  return line
}

function clampValue(v: unknown): unknown {
  if (typeof v === 'string') return v.slice(0, MAX_STR)
  if (typeof v === 'number' || typeof v === 'boolean' || v === null) return v
  try {
    return JSON.stringify(v).slice(0, MAX_STR)
  } catch {
    return String(v).slice(0, MAX_STR)
  }
}

function allow(ip: string): boolean {
  const now = Date.now()
  const b = buckets.get(ip)
  if (!b || b.reset < now) {
    buckets.set(ip, { count: 1, reset: now + 60_000 })
    if (buckets.size > 5_000) {
      for (const [k, v] of buckets) if (v.reset < now) buckets.delete(k)
    }
    return true
  }
  b.count += 1
  return b.count <= RATE_PER_MIN
}
