// Nitro event.context 的自訂欄位（server/plugins/logging.ts 寫入、useApi 與 log 端點讀取）
declare module 'h3' {
  interface H3EventContext {
    requestId?: string
    startedAt?: number
    userId?: string
  }
}

export {}
