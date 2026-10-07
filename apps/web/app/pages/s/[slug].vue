<script setup lang="ts">
import type { SharePage } from '~/types/api'
import { paletteGradient, tagStyle } from '~/utils/labels'

/**
 * 分享頁（FR-4）：SSR + OG meta，訪客一律看不到 NSFW（由後端過濾）。
 */
const route = useRoute()
const api = useApi()
const config = useRuntimeConfig()
const { me } = useAuth()
const { t } = useI18n()
const { visibilityLabel } = useLabels()
const slug = route.params.slug as string

const { data: page, error } = await useAsyncData(`share-${slug}`, () => api<SharePage>(`/share/${encodeURIComponent(slug)}`))
if (error.value || !page.value) {
  throw createError({ statusCode: 404, statusMessage: t('share.notFound') })
}

const f = computed(() => page.value!.fursona)
const owner = computed(() => page.value!.owner)
const subtitle = computed(() => [f.value.species, ...f.value.tags.slice(0, 2)].filter(Boolean).join(' · '))
const pageTitle = computed(() => (f.value.species
  ? t('share.seo.titleWithSpecies', { name: f.value.name, species: f.value.species })
  : f.value.name))
const description = computed(() => {
  const bio = (f.value.bio ?? '').split('\n').map(s => s.trim()).filter(Boolean)[0] ?? ''
  const summary = t('share.seo.summary', {
    palette: t('share.seo.paletteCount', f.value.palette.length),
    media: t('share.seo.mediaCount', f.value.media?.length ?? 0)
  })
  return bio ? `${bio} ${summary}` : summary
})
const ogImage = computed(() => page.value!.og_image_url ? `${config.public.siteUrl}${page.value!.og_image_url}` : undefined)

useSeoMeta({
  title: pageTitle,
  description,
  ogTitle: () => t('share.seo.ogTitle', { title: pageTitle.value }),
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

// 可嵌入時輸出 oEmbed discovery link，讓 WordPress／Notion 等貼連結時取得卡片（FR-7.2）
useHead(() => ({
  link: page.value?.embed_enabled
    ? [{
        rel: 'alternate',
        type: 'application/json+oembed',
        href: `${config.public.siteUrl}/api/oembed?format=json&url=${encodeURIComponent(page.value.share.url)}`,
        title: `${f.value.name} · Pawfit`
      }]
    : []
}))

const bioParagraphs = computed(() => (f.value.bio ?? '').split(/\n{2,}/).map(s => s.trim()).filter(Boolean))
const reporting = ref(false)

const nsfwHint = computed(() => {
  if (!me.value) return t('share.nsfw.guest')
  if (!me.value.adult_confirmed_at) return t('share.nsfw.unconfirmed')
  return t('share.nsfw.hidden')
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
        :alt="t('share.header.avatarAlt', { name: f.name })"
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
          />{{ t('share.header.ownerFursona', { id: owner.pawfit_id }) }}
        </NuxtLink>
        <div
          v-if="f.tags.length"
          class="tags"
        >
          <span
            v-for="(tag, i) in f.tags"
            :key="tag"
            class="tag"
            :class="tagStyle(i).class"
            :style="tagStyle(i).style"
          >{{ tag }}</span>
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
            {{ t('share.about.title', { name: f.name.split(' ')[0] }) }}
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
            <span class="disp">{{ t('share.palette.title') }}</span><em>{{ t('share.palette.hint') }}</em>
          </div>
          <PaletteChips :palette="f.palette" />
        </div>
        <div
          v-if="!bioParagraphs.length && !f.palette.length"
          class="empty"
        >
          {{ t('share.emptyBioPalette') }}
        </div>
      </aside>

      <section class="card">
        <h2 class="disp">
          {{ t('share.gallery.title') }}
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
            {{ t('share.gallery.empty') }}
          </template>
        </GalleryGrid>
      </section>
    </div>

    <div class="share">
      <span>{{ t('share.footer.shareLink') }} <b class="mono">{{ page.share.url.replace(/^https?:\/\//, '') }}</b></span>
      <span>{{ t('share.footer.watermark') }} <b>{{ page.share.watermark ? t('share.footer.on') : t('share.footer.off') }}</b></span>
      <span
        class="sp"
        style="flex:1"
      />
      <span>{{ f.visibility === 'public' ? t('share.footer.publicOnProfile', { visibility: visibilityLabel(f.visibility) }) : visibilityLabel(f.visibility) }}</span>
      <NuxtLink
        v-if="page.is_owner"
        class="btn sm"
        :to="`/fursona/${f.id}#privacy`"
      >
        {{ t('share.footer.manage') }}
      </NuxtLink>
      <button
        v-else
        class="btn sm ghost"
        type="button"
        @click="reporting = true"
      >
        {{ t('share.footer.report') }}
      </button>
    </div>

    <ReportDialog
      v-model:open="reporting"
      target-type="fursona"
      :target-id="f.id"
      :target-label="t('share.reportTarget', { name: f.name })"
    />
  </section>
</template>
