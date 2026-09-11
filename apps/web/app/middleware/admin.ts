export default defineNuxtRouteMiddleware(() => {
  const { me } = useAuth()
  if (!me.value?.is_admin) {
    // 不洩漏後台存在：一律 404
    throw createError({ statusCode: 404, statusMessage: 'Not Found' })
  }
})
