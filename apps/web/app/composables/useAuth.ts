import type { Me } from '~/types/api'

export function useAuth() {
  const me = useState<Me | null>('auth:me', () => null)
  const loaded = useState<boolean>('auth:loaded', () => false)
  const api = useApi()

  async function fetchMe(force = false): Promise<Me | null> {
    if (loaded.value && !force) return me.value
    try {
      me.value = await api<Me>('/me')
    } catch {
      me.value = null
    }
    loaded.value = true
    return me.value
  }

  async function logout() {
    try {
      await api('/auth/logout', { method: 'POST' })
    } finally {
      me.value = null
      await navigateTo('/')
    }
  }

  /** 訪客或未完成 18+ 聲明一律 hide。 */
  const effectiveNsfwPref = computed(() => me.value?.effective_nsfw_pref ?? 'hide')

  return {
    me,
    loaded,
    isLoggedIn: computed(() => !!me.value),
    isAdmin: computed(() => !!me.value?.is_admin),
    effectiveNsfwPref,
    fetchMe,
    logout
  }
}
