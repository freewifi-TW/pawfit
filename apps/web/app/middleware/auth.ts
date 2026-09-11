export default defineNuxtRouteMiddleware((to) => {
  const { me } = useAuth()
  if (!me.value) {
    return navigateTo({ path: '/login', query: { next: to.fullPath } })
  }
})
