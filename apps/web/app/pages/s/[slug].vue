<script setup lang="ts">
import type { SharePage } from '~/types/api'
import { paletteGradient, tagStyle, VISIBILITY_LABEL } from '~/utils/labels'

/**
 * 分享頁（FR-4）：SSR + OG meta，訪客一律看不到 NSFW（由後端過濾）。
 */
const route = useRoute()
const api = useApi()
const config = useRuntimeConfig()
const { me } = useAuth()
const slug = route.params.slug as string

const { data: page, error } = await useAsyncData(`share-${slug}`, () => api<SharePage>(`/share/${encodeURIComponent(slug)}`))
if (error.value || !page.value) {
  throw createError({ statusCode: 404, statusMessage: '連結可能已停用或獸設已設為私人' })
}

const f = computed(() => page.value!.fursona)
const owner = computed(() => page.value!.owner)
const subtitle = computed(() => [f.value.species, ...f.value.tags.slice(0, 2)].filter(Boolean).join(' · '))
const description = computed(() => {
  const bio = (f.value.bio ?? '').split('\n').map(s => s.trim()).filter(Boolean)[0] ?? ''
  return `${bio ? `${bio} ` : ''}${f.value.palette.length} 色色票 · ${f.value.media?.length ?? 0} 張設定圖 — Pawfit`
})
const ogImage = computed(() => page.value!.og_image_url ? `${config.public.siteUrl}${page.value!.og_image_url}` : undefined)

useSeoMeta({
  title: () => `${f.value.name}${f.value.species ? ` · ${f.value.species}` : ''}`,
  description,
  ogTitle: () => `${f.value.name}${f.value.species ? ` · ${f.value.species}` : ''} — Pawfit 獸設分享`,
  ogDescription: description,
  ogImage,
  ogUrl: () => page.value!.share.url,
  twitterCard: 'summary_large_image',
  twitterTitle: () => f.value.name,
  twitterDescription: description,
  twitterImage: ogImage,
  // 連結可見的內容不進搜尋引擎
  robots: () => (f.value.visibility === 'unlisted' ? 'noindex, nofollow' : 'index, follow')
})

const bioParagraphs = computed(() => (f.value.bio ?? '').split(/\n{2,}/).map(s => s.trim()).filter(Boolean))
const reporting = ref(false)

const nsfwHint = computed(() => {
  if (!me.value) return '登入並在設定中完成 18 歲聲明後即可選擇顯示方式。'
  if (!me.value.adult_confirmed_at) return '到「設定」完成 18 歲聲明後即可選擇顯示方式。'
  return '你的顯示偏好為「完全隱藏」，可在「設定」中調整。'
})
</script>

<template>
  <section
    v-if="page"
    class="wrap"
    style="padding-bottom:48px"
  >
    <section class="profile">
      <BlobAvatar
        :src="f.avatar_url"
        :gradient="paletteGradient(f.palette)"
        :size="200"
        :border="6"
        :alt="`${f.name} 的頭像`"
      />
      <div>
        <h1 class="disp">
          {{ f.name }}<small v-if="subtitle">{{ subtitle }}</small>
        </h1>
        <NuxtLink
          class="owner"
          :to="`/u/${owner.pawfit_id}`"
        >
          <BlobAvatar
            :src="owner.avatar_url"
            :size="22"
            :border="0"
            variant="user"
          />@{{ owner.pawfit_id }} 的獸設
        </NuxtLink>
        <div
          v-if="f.tags.length"
          class="tags"
        >
          <span
            v-for="(t, i) in f.tags"
            :key="t"
            class="tag"
            :class="tagStyle(i).class"
            :style="tagStyle(i).style"
          >{{ t }}</span>
        </div>
      </div>
    </section>

    <div class="board">
      <aside class="stack">
        <div
          v-if="bioParagraphs.length"
          class="card"
        >
          <h2 class="disp">
            關於{{ f.name.split(' ')[0] }}
          </h2>
          <div class="bd bio">
            <p
              v-for="(p, i) in bioParagraphs"
              :key="i"
            >
              {{ p }}
            </p>
          </div>
        </div>
        <div
          v-if="f.palette.length"
          class="card"
        >
          <div class="hd">
            <span class="disp">色票</span><em>點一下複製色碼</em>
          </div>
          <PaletteChips :palette="f.palette" />
        </div>
        <div
          v-if="!bioParagraphs.length && !f.palette.length"
          class="empty"
        >
          還沒有簡介與色票。
        </div>
      </aside>

      <section class="card">
        <h2 class="disp">
          圖庫
        </h2>
        <GalleryGrid
          :media="f.media ?? []"
          :owner="page.is_owner"
          :hidden-nsfw="page.hidden_nsfw_count"
        >
          <template #nsfw-hint>
            {{ nsfwHint }}
          </template>
          <template #empty>
            目前沒有可顯示的圖片。
          </template>
        </GalleryGrid>
      </section>
    </div>

    <div class="share">
      <span>🔗 分享連結 <b class="mono">{{ page.share.url.replace(/^https?:\/\//, '') }}</b></span>
      <span>浮水印 <b>{{ page.share.watermark ? '開啟' : '關閉' }}</b></span>
      <span
        class="sp"
        style="flex:1"
      />
      <span>{{ VISIBILITY_LABEL[f.visibility] }}<template v-if="f.visibility === 'public'"> · 顯示於個人主頁</template></span>
      <NuxtLink
        v-if="page.is_owner"
        class="btn sm"
        :to="`/fursona/${f.id}#privacy`"
      >
        管理連結
      </NuxtLink>
      <button
        v-else
        class="btn sm ghost"
        type="button"
        @click="reporting = true"
      >
        檢舉此頁
      </button>
    </div>

    <ReportDialog
      v-model:open="reporting"
      target-type="fursona"
      :target-id="f.id"
      :target-label="`獸設「${f.name}」`"
    />
  </section>
</template>
