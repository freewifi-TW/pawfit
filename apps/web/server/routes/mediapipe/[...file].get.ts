import { createReadStream, statSync } from 'node:fs'
import { join, normalize } from 'node:path'

/**
 * GET /mediapipe/<file> → 從 node_modules 提供 @mediapipe/tasks-vision 的 WASM／loader（FR-B8.5：模型與執行檔自站提供，不打第三方）。
 * 檔案約 34MB，不進 git；dev 與 prod 映像都有 node_modules。
 */
const ALLOWED = /^vision_wasm_(?:internal|module_internal|nosimd_internal)\.(?:js|wasm)$/

export default defineEventHandler((event) => {
  const file = (getRouterParam(event, 'file') ?? '').split('/').pop() ?? ''
  if (!ALLOWED.test(file)) {
    throw createError({ statusCode: 404, statusMessage: 'Not found' })
  }
  const path = normalize(join(process.cwd(), 'node_modules', '@mediapipe', 'tasks-vision', 'wasm', file))
  let size: number
  try {
    size = statSync(path).size
  } catch {
    throw createError({ statusCode: 404, statusMessage: 'Not found' })
  }
  setResponseHeaders(event, {
    'Content-Type': file.endsWith('.wasm') ? 'application/wasm' : 'text/javascript; charset=utf-8',
    'Content-Length': String(size),
    'Cache-Control': 'public, max-age=604800, immutable',
    'X-Robots-Tag': 'noindex'
  })
  return sendStream(event, createReadStream(path))
})
