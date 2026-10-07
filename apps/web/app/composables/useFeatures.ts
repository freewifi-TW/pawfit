/**
 * 後端 feature flag（GET /api/features，對應 config/pawfit.php 的 features）。
 * 由 plugins/01.auth.ts 在 SSR 時取得一次並水合；全部預設 false，拿不到時也不影響 Phase 1 功能。
 */
export interface Features {
  dev_login: boolean
  friends: boolean
  feed: boolean
  head_sticker: boolean
  wardrobe: boolean
  brief_llm: boolean
}

const DEFAULTS: Features = { dev_login: false, friends: false, feed: false, head_sticker: false, wardrobe: false, brief_llm: false }

export function useFeatures() {
  const features = useState<Features>('features', () => ({ ...DEFAULTS }))
  const loaded = useState<boolean>('features:loaded', () => false)
  const api = useApi()

  async function fetchFeatures(force = false) {
    if (loaded.value && !force) return features.value
    try {
      features.value = { ...DEFAULTS, ...(await api<Partial<Features>>('/features')) }
    } catch {
      features.value = { ...DEFAULTS }
    }
    loaded.value = true
    return features.value
  }

  return {
    features,
    fetchFeatures,
    friends: computed(() => features.value.friends),
    feed: computed(() => features.value.feed)
  }
}
