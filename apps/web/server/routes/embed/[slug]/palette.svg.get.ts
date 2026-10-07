/**
 * GET /embed/:slug/palette.svg → 代理到 Laravel 的 SVG 色票卡（FR-7.3）。
 * /embed/* 由 Nuxt 負責，但 SVG 在 API 端渲染與快取；這裡只轉送，不帶任何瀏覽器 cookie。
 */
const SLUG = /^[A-Za-z0-9]{6,32}$/

export default defineEventHandler((event) => {
  const slug = getRouterParam(event, 'slug') ?? ''
  if (!SLUG.test(slug)) {
    throw createError({ statusCode: 404, statusMessage: 'Not found' })
  }

  const config = useRuntimeConfig()
  const query = getQuery(event)
  const qs = new URLSearchParams()
  for (const key of ['theme', 'layout'] as const) {
    const v = query[key]
    if (typeof v === 'string' && /^[a-z]+$/.test(v)) qs.set(key, v)
  }
  const target = `${config.apiInternalBase}/v1/public/fursonas/${slug}/palette.svg${qs.size ? `?${qs}` : ''}`

  return sendProxy(event, target, {
    headers: {
      accept: 'image/svg+xml',
      ...(event.context.requestId ? { 'x-request-id': event.context.requestId } : {})
    },
    fetchOptions: { redirect: 'manual' }
  })
})
