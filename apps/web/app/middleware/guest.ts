export default defineNuxtRouteMiddleware(() => {
  const { me } = useAuth()
  if (me.value) {
    return navigateTo(me.value.is_onboarded ? '/dashboard' : '/onboarding')
  }
})
