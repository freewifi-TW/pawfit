/**
 * 統一 log 格式（docs/logging.md）— Nuxt 端共用（server 與 browser）。
 *
 *   ts, level, service, event, message, request_id, user_id, context{...}, exception{...}
 *
 * server 端直接寫一行 JSON 到 stdout，由 Alloy 收進 Loki。
 * browser 端不會直接輸出，而是把事件 POST 到 /_log/client，由 server 端代寫（service=browser）。
 */

export type LogLevel = 'debug' | 'info' | 'warning' | 'error'

export const LOG_LEVELS: readonly LogLevel[] = ['debug', 'info', 'warning', 'error']

export interface SerializedError {
  class: string
  message: string
  code?: string | number
  stack?: string[]
}

export interface LogLine {
  ts: string
  level: LogLevel
  service: string
  event: string
  message: string
  request_id: string | null
  user_id: string | null
  context: Record<string, unknown>
  exception?: SerializedError
}

export interface LogInput {
  request_id?: string | null
  user_id?: string | null
  context?: Record<string, unknown>
  error?: unknown
  service?: string
}

const STACK_FRAMES = 15

export function serializeError(err: unknown): SerializedError {
  if (err instanceof Error) {
    const out: SerializedError = { class: err.name || 'Error', message: err.message }
    const code = (err as { code?: unknown, statusCode?: unknown }).statusCode ?? (err as { code?: unknown }).code
    if (typeof code === 'string' || typeof code === 'number') out.code = code
    if (err.stack) {
      out.stack = err.stack.split('\n').slice(1, 1 + STACK_FRAMES).map(s => s.trim())
    }
    return out
  }
  if (typeof err === 'object' && err !== null) {
    const o = err as Record<string, unknown>
    return {
      class: typeof o.name === 'string' ? o.name : 'Object',
      message: typeof o.message === 'string' ? o.message : safeString(err)
    }
  }
  return { class: typeof err, message: safeString(err) }
}

export function buildLogLine(level: LogLevel, event: string, message: string, input: LogInput = {}): LogLine {
  const line: LogLine = {
    ts: new Date().toISOString(),
    level,
    service: input.service ?? 'web',
    event,
    message,
    request_id: input.request_id ?? null,
    user_id: input.user_id ?? null,
    context: input.context ?? {}
  }
  if (input.error !== undefined) line.exception = serializeError(input.error)
  return line
}

/** server 端：寫一行到 stdout。browser 端呼叫不會有任何動作。 */
export function logServer(level: LogLevel, event: string, message: string, input: LogInput = {}): void {
  if (import.meta.client) return
  writeLine(buildLogLine(level, event, message, input))
}

export function writeLine(line: LogLine): void {
  if (import.meta.client) return
  try {
    // shared/ 同時編進 browser bundle，沒有 Node 型別，所以從 globalThis 取 process
    const stdout = (globalThis as { process?: { stdout?: { write: (chunk: string) => unknown } } }).process?.stdout
    stdout?.write(JSON.stringify(line) + '\n')
  } catch {
    // stdout 壞掉就算了，不能讓 log 影響請求
  }
}

export function levelForStatus(status: number): LogLevel {
  if (status >= 500) return 'error'
  if (status >= 400) return 'warning'
  return 'info'
}

/** 去掉 query string，避免把 token / 簽名寫進 log */
export function pathOnly(url: string): string {
  const i = url.indexOf('?')
  return i === -1 ? url : url.slice(0, i)
}

function safeString(v: unknown): string {
  try {
    return typeof v === 'string' ? v : JSON.stringify(v) ?? String(v)
  } catch {
    return String(v)
  }
}
