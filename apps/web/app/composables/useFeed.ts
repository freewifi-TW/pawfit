import type { FeedPage, Post } from '~/types/api'

/**
 * cursor 分頁的貼文列表（好友河道、探索河道、個人主頁貼文）。
 * 首頁 SSR 取第一頁，之後「載入更多」在瀏覽器端追加。
 */
export async function useFeed(key: string, path: () => string) {
  const api = useApi()
  const posts = ref<Post[]>([])
  const nextCursor = ref<string | null>(null)
  const loading = ref(false)
  const error = ref<string | null>(null)

  const { data, refresh, pending } = await useAsyncData(key, () => api<FeedPage>(path()), {
    default: () => ({ data: [], next_cursor: null })
  })
  watch(data, (d) => {
    posts.value = d?.data ?? []
    nextCursor.value = d?.next_cursor ?? null
  }, { immediate: true })

  async function loadMore() {
    if (!nextCursor.value || loading.value) return
    loading.value = true
    error.value = null
    try {
      const sep = path().includes('?') ? '&' : '?'
      const page = await api<FeedPage>(`${path()}${sep}cursor=${encodeURIComponent(nextCursor.value)}`)
      posts.value = [...posts.value, ...page.data]
      nextCursor.value = page.next_cursor
    } catch (e) {
      error.value = apiError(e).message
    } finally {
      loading.value = false
    }
  }

  function replace(post: Post) {
    posts.value = posts.value.map(p => (p.id === post.id ? post : p))
  }
  function remove(id: string) {
    posts.value = posts.value.filter(p => p.id !== id)
  }

  return { posts, hasMore: computed(() => !!nextCursor.value), loading, pending, error, loadMore, refresh, replace, remove }
}
