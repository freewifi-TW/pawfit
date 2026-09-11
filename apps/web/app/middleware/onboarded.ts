/** 需登入且已完成 Pawfit ID 與條款；停權帳號導回首頁。 */
export default defineNuxtRouteMiddleware((to) => {
  const { me } = useAuth()
  if (!me.value) {
    return navigateTo({ path: '/login', query: { next: to.fullPath } })
  }
  if (me.value.is_banned) {
    return navigateTo('/?banned=1')
  }
  if (!me.value.is_onboarded) {
    return navigateTo('/onboarding')
  }
})
